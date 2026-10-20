#!/usr/bin/env python3
# cspell:words buildx BUILDKIT cacheonly pipefail configtest
"""Publish prepared images and record phase timings without exposing credentials."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import time


def run(args, name, capture=False):
    started = time.monotonic()
    chunks = []
    with (output / (name + '.log')).open('w') as log:
        process = subprocess.Popen(args, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
        for line in process.stdout:
            chunks.append(line)
            log.write(line)
            log.flush()
            if not capture:
                print(line, end='', flush=True)
        returncode = process.wait()
    text = ''.join(chunks)
    report['operations'][name] = {
        'seconds': round(time.monotonic() - started, 3), 'exit_code': returncode,
    }
    save()
    if returncode:
        if capture:
            print(text[-6000:])
        raise RuntimeError(f'{name} failed with exit code {returncode}')
    if not capture:
        print(f'{name}: {report["operations"][name]["seconds"]}s', flush=True)
    return text


def save():
    (output / 'report.json').write_text(json.dumps(report, indent=2) + '\n')


def bake(name, extra):
    args = ['docker', 'buildx', 'bake', '--builder', state['builder'], '-f',
            '.circleci/images/docker-bake.hcl', '--progress', 'plain']
    if state['cache_from']:
        args += ['--set', '*.cache-from=type=registry,ref=' + state['cache_from']]
    return run(args + extra, name)


def inspect(reference, name):
    manifest = json.loads(run(['docker', 'buildx', 'imagetools', 'inspect', '--raw', reference], name, True))
    if 'manifests' in manifest:
        digest = next(m['digest'] for m in manifest['manifests']
                      if m.get('platform', {}).get('architecture') == 'amd64')
        manifest = json.loads(run(['docker', 'buildx', 'imagetools', 'inspect', '--raw',
                                   state['repository'] + '@' + digest], name + '-amd64', True))
    return manifest


def build_metrics(log):
    # Nested exporter timings overlap: retain each measurement rather than summing.
    targets = {}
    result = {'cache_manifest_import_seconds': [], 'export_layer_seconds': {},
              'push_layer_seconds': {}, 'push_manifest_seconds': {}}
    cache_vertices = set()
    for line in log.splitlines():
        match = re.match(r'#(\d+) \[(apache|browser)\] exporting to image', line)
        if match:
            targets[match[1]] = match[2]
        match = re.match(r'#(\d+) (?:\[[^\]]+\] )?importing cache manifest from ', line)
        if match:
            cache_vertices.add(match[1])
        match = re.match(r'#(\d+) DONE ([\d.]+)s', line)
        if match and match[1] in cache_vertices:
            result['cache_manifest_import_seconds'].append(float(match[2]))
        for text, key in [('exporting layers', 'export_layer_seconds'),
                          ('pushing layers', 'push_layer_seconds'),
                          ('pushing manifest for .+', 'push_manifest_seconds')]:
            match = re.match(r'#(\d+) ' + text + r' ([\d.]+)s done', line)
            if match and match[1] in targets:
                result[key][targets[match[1]]] = float(match[2])
    return result


parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('operation', choices=['prepare', 'build', 'cache', 'verify', 'cleanup'])
parser.add_argument('--phase', choices=['cold', 'warm', 'browser-refresh'], required=True)
parser.add_argument('--trial-id', required=True)
parser.add_argument('--repository', default=os.environ.get('IMAGE_REPOSITORY'))
parser.add_argument('--output', default='test-results/image-maintenance')
args = parser.parse_args()
if not args.repository or not re.fullmatch(r'[a-zA-Z0-9./:_-]+', args.repository):
    parser.error('Set IMAGE_REPOSITORY or --repository to the publishing destination')
if not re.fullmatch(r'[a-zA-Z0-9-]+', args.trial_id):
    parser.error('trial-id must contain only letters, numbers and hyphens')
output = Path(args.output)
output.mkdir(parents=True, exist_ok=True)
state_path = output / 'state.json'
report_path = output / 'report.json'
report = json.loads(report_path.read_text()) if report_path.exists() else {'operations': {}}
state = json.loads(state_path.read_text()) if state_path.exists() else {}

if args.operation == 'prepare':
    revision = subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip()
    build_id = os.environ.get('CIRCLE_BUILD_NUM', str(time.time_ns()))
    if not re.fullmatch(r'[a-zA-Z0-9-]+', build_id):
        raise ValueError('Invalid build identifier')
    release = f'ci-{revision[:8]}-{args.trial_id}-{args.phase}-{build_id}'
    cache_prefix = args.repository + ':ci-cache-' + args.trial_id
    previous = {'cold': '', 'warm': 'cold', 'browser-refresh': 'warm'}[args.phase]
    state = {'revision': revision, 'phase': args.phase, 'repository': args.repository,
             'release': release, 'builder': 'ci-images-' + build_id,
             'cache_from': cache_prefix + '-' + previous if previous else '',
             'cache_to': cache_prefix + '-' + args.phase}
    state_path.write_text(json.dumps(state, indent=2) + '\n')
    report['state'] = state
    save()
    run(['docker', 'version'], 'docker-version')
    run(['docker', 'buildx', 'version'], 'buildx-version')
    create = ['docker', 'buildx', 'create', '--name', state['builder'], '--driver',
              'docker-container', '--bootstrap']
    # Local unauthenticated registry checks may explicitly provide their config.
    if os.environ.get('IMAGE_BUILDKIT_CONFIG'):
        create += ['--buildkitd-config', os.environ['IMAGE_BUILDKIT_CONFIG'],
                   '--driver-opt', 'network=host']
    run(create, 'builder-prepare')
    if state['cache_from']:
        run(['docker', 'buildx', 'imagetools', 'inspect', state['cache_from']], 'cache-source-exists')
else:
    if not state:
        if args.operation == 'cleanup':
            raise SystemExit(0)
        raise RuntimeError('Run prepare first')
    if state['phase'] != args.phase or state['repository'] != args.repository:
        raise RuntimeError('State belongs to a different trial phase or repository')
    os.environ['IMAGE_REPOSITORY'] = state['repository']
    os.environ['RELEASE'] = state['release']
    if args.operation == 'build':
        extra = ['--set', '*.output=type=registry', '--metadata-file', str(output / 'images-metadata.json')]
        if args.phase == 'cold':
            extra += ['--no-cache']
        elif args.phase == 'browser-refresh':
            extra += ['--set', 'browser.no-cache-filter=browser']
        log = bake('build-and-publish', extra + ['default'])
        report['build_metrics'] = build_metrics(log)
        save()
    elif args.operation == 'cache':
        bake('cache-export', ['--set', '*.output=type=cacheonly', '--set',
                              'browser.cache-to=type=registry,ref=' + state['cache_to'] + ',mode=max',
                              'browser'])
        cache = json.loads(run(['docker', 'buildx', 'imagetools', 'inspect', '--raw',
                               state['cache_to']], 'cache-manifest', True))
        (output / 'cache-descriptors.json').write_text(json.dumps(cache, indent=2) + '\n')
        report['cache_compressed_layer_bytes'] = sum(layer['size'] for layer in cache.get('layers', []))
        # Descriptor bytes are cache contents, not necessarily newly uploaded bytes.
        save()
    elif args.operation == 'verify':
        images = {target: state['repository'] + ':' + state['release'] + '-' + target
                  for target in ['apache', 'browser']}
        manifests = {target: inspect(ref, target + '-manifest') for target, ref in images.items()}
        apache_layers = manifests['apache']['layers']
        browser_layers = manifests['browser']['layers']
        if apache_layers != browser_layers[:len(apache_layers)]:
            raise RuntimeError('Browser output does not extend the exact published Apache layers')
        report['exact_parent_verified'] = True
        report['published_references'] = images
        report['image_compressed_bytes'] = {t: sum(x['size'] for x in m['layers'])
                                            for t, m in manifests.items()}
        save()
        for target, ref in images.items():
            run(['docker', 'pull', ref], target + '-pull')
            run(['docker', 'run', '--rm', ref, 'bash', '-eo', 'pipefail', '-c',
                 'test "$(id -un)" = circleci; php --version; node --version; sudo apache2ctl configtest'],
                target + '-smoke')
        smoke = state['builder'] + '-smoke'
        try:
            run(['docker', 'create', '--name', smoke, '--init', '--shm-size', '2g',
                 images['browser'], 'sleep', '300'], 'browser-create')
            run(['docker', 'start', smoke], 'browser-start')
            run(['docker', 'exec', smoke, 'mkdir', '-p', '/tmp/runner'], 'runner-directory')
            for filename in ['package.json', 'package-lock.json']:
                run(['docker', 'cp', '.circleci/playwright/' + filename,
                     smoke + ':/tmp/runner/' + filename], 'runner-copy-' + filename)
            run(['docker', 'exec', '--workdir', '/tmp/runner', smoke, 'npm', 'ci',
                 '--no-audit', '--no-fund', '--workspaces=false'], 'runner-install')
            run(['docker', 'exec', '--workdir', '/tmp/runner', smoke, 'node', '-e',
                 'const {chromium}=require("playwright"); '
                 '(async()=>{const b=await chromium.launch(); console.log(b.version()); '
                 'await b.close()})().catch(e=>{console.error(e);process.exit(1)})'], 'chromium-smoke')
        finally:
            subprocess.run(['docker', 'rm', '-f', '-v', smoke], stdout=subprocess.DEVNULL,
                           stderr=subprocess.DEVNULL)
        report['smoke_verified'] = True
        save()
    elif args.operation == 'cleanup':
        run(['docker', 'buildx', 'rm', state['builder']], 'builder-cleanup')

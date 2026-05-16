<?php

declare(strict_types=1);

use FOSSBilling\Doctrine\EntityManagerFactory;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[Group('Core')]
final class FOSSBilling_Doctrine_EntityManagerFactoryTest extends BBTestCase
{
    public function testCacheNamespaceSeedChangesWhenEntityDefinitionChanges(): void
    {
        $filesystem = new Filesystem();
        $directory = Path::join(sys_get_temp_dir(), 'fb-doctrine-seed-' . bin2hex(random_bytes(8)));
        $file = Path::join($directory, 'Currency.php');

        try {
            $filesystem->mkdir($directory);
            $filesystem->dumpFile($file, "<?php\nfinal class Currency {}\n");

            $seedBefore = $this->invokePrivateStaticMethod('getCacheNamespaceSeed', [[$directory]]);

            sleep(1);
            $filesystem->dumpFile($file, "<?php\nfinal class Currency { public string \$code; }\n");

            $seedAfter = $this->invokePrivateStaticMethod('getCacheNamespaceSeed', [[$directory]]);

            $this->assertNotSame($seedBefore, $seedAfter);
        } finally {
            $filesystem->remove($directory);
        }
    }

    private function invokePrivateStaticMethod(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass(EntityManagerFactory::class);
        $methodReflection = $reflection->getMethod($method);

        return $methodReflection->invokeArgs(null, $args);
    }
}

# Contributing to FOSSBilling

:+1::tada: First off, thanks for expressing an interest in contributing to FOSSBilling! :tada::+1:

Whether open-source projects live or die completely depends on community participation and involvement. This one is no exception, so we appreciate and welcome every contribution.

The document is a set of guidelines for contributing to FOSSBilling code and documentation, which are hosted in the [FOSSBilling Organization](https://github.com/FOSSBilling) on GitHub. These are strong guidelines but not set in stone rules. Please use your best judgement, feel free to propose changes to this document in a pull request, and don't be afraid to ask questions.

We've tried to keep this document as short as possible but there is a lot of information to get through. If you are looking for something specific then you can use the table of contents to skip to that section:

#### Contents

[Why should I contribute to FOSSBilling?](#why-should-i-contribute)

[Before you get started](#before-you-get-started)
 * [Code of Conduct](#code-of-conduct)
 * [Which 'branch' should I contribute to?](#which-branch-should-i-contribute-to)
 * [Understanding the structure of FOSSBilling](#)

[How can I contribute?](#how-can-i-contribute)
 * [Reporting bugs](#reporting-bugs)
 * [Suggesting improvements](#suggesting-improvements)
 * [Contributing Code](#contributing-code)
 * [Writing Documentation](#writing-documentation)
 * [Translating FOSSBilling](#translating-fossbilling)
 * [Sponsor the project](#sponsoring-the-project)

[Style Guides](#styleguides)

[But, I still have a question!](#but-i-still-have-a-question)


## Why should I contribute?

In one simple sentence, every contribution means not just that you give something back to the community but also that you get to use and enjoy better software. 

If you need more reasons than that though, then because...

* __Shape the project's future__. There are a lot of open issues in our GitHub repo, by taking part in the discussion and submitting code contributions to work on the ones that are most important to you, you get to shape the future of the project.
* __Develop your skills__. Whether you are writing PHP or writing documentation, being a part of a collaborative project with others actively reviewing your work helps you to build your skills.
* __It's fun__. We are transforming an outdated piece of software and building something modern and exciting, there are going to be a lot of challenges along the way, and taking part in solving them is fun and satisfying.

## Before you get started

### Code of Conduct

First off, no matter how you plan to take part, please take a couple of minutes to read our code of conduct before contributing anything.

This project and everyone participating in it are governed by the [FOSSBilling Code of Conduct](.github/CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to the moderators on our [Discord](https://fossbilling.org/discord) server.

### Understanding the structure of FOSSBilling

FOSSBilling is an open-source project &mdash; it's made up of over [50 modules](https://github.com/FOSSBilling/FOSSBilling/tree/master/src/modules). When you initially consider contributing to FOSSBilling, you might be unsure about which of those 50 modules implements the functionality you want to change or report a bug for. This section should help you with that.

There are 2 types of modules:

* Service modules
* All other modules

Think of Service modules as products that you want to sell. These modules have actions related to product configuration. Let's say the `Servicedownloadable` module allows you to sell downloadable products such as e-books, images, photos, and documents. The module keeps track of the number of downloads, and how many downloads were made.
If you need to sell a new type of product you will implement a Service type module.

Other modules extend the whole FOSSBilling API with any functionality needed. Check existing modules to get an idea of what is already shipped with the default structure of FOSSBilling.


## How can I contribute?

There are a lot of different ways that you can get involved in the FOSSBilling project. Let's take a look at some of the main ones:

### Reporting bugs

If you find a bug in FOSSBilling, please report it. Following these guidelines helps maintainers and the community understand your report :pencil:, reproduce the behavior :computer:, and find related reports :mag_right:.

#### Before submitting a bug report

⚠️ If your report is for a potential security exploit, please do not make it public by creating an Issue, but instead, follow the instructions in our [Security Policy](https://github.com/FOSSBilling/FOSSBilling/security/policy).

Firstly, **Do a [search](https://github.com/search?q=+is%3Aissue+user%3Afossbilling)** of the existing issues to see if the problem has already been reported. If it has **and the issue is still open**, add a comment to the existing issue instead of opening a new one.

> **Note:** If you find a **Closed** issue that seems like it is the same thing that you're experiencing, open a new issue and include a link to the original issue in the body of your new one.

#### How do I submit a (good) bug report?

Bugs are tracked as [GitHub issues](https://guides.github.com/features/issues/). After you've determined which module your bug is related to, create an issue and provide the following information by filling in [the template](https://github.com/FOSSBilling/.github/blob/master/.github/ISSUE_TEMPLATE/bug_report.md).

Explain the problem and include additional details to help maintainers reproduce the problem:

* **Use a clear and descriptive title** for the issue to identify the problem.
* **Describe the exact steps which reproduce the problem** in as much detail as possible. For example, start by explaining what section exactly you used in the browser, or which API call you were using. When listing steps, **don't just say what you did but explain how you did it**.
* **Provide specific examples to demonstrate the steps**. Include links to files or GitHub projects, or copy/pastable snippets, which you use in those examples. If you're providing snippets in the issue, use [Markdown code blocks](https://help.github.com/articles/markdown-basics/#multiple-lines).
* **Describe the behavior you observed after following the steps** and point out what exactly is the problem with that behavior.
* **Explain which behavior you expected to see instead and why.**
* **Include screenshots and animated GIFs** which show you following the described steps and demonstrate the problem. 
* **If the problem wasn't triggered by a specific action**, describe what you were doing before the problem happened and share more information using the guidelines below.

Provide more context by answering these questions:

* **Can you reliably reproduce the issue?** If not, provide details about how often the problem happens and under which conditions it normally happens.
* If the problem is related to working with files (e.g. opening and editing files), **does the problem happen for all files and projects or only some?** Does the problem happen only when working with local or remote files (e.g. on network drives), with files of a specific type (e.g. only JavaScript or Python files), with large files or files with very long lines, or with files in a specific encoding? Is there anything else special about the files you are using?

Include details about your configuration and environment:

* **Which version of FOSSBilling are you using?** You can get the exact version by running `https://<your domain>/api/guest/system/version` in your browser.
* **What's the name and version of the server OS you're FOSSBilling installation is running**?
* **What's the PHP version your server is using**?
* **What's the MySQL version your server is using**?
* **What's the Web Server and version you're using**?

### Suggesting improvements or new features

⚠️ Please note the title is __*Suggesting*__, not __*Demanding*__. Be polite, appreciate other people's time, and explain in detail, and you are far more likely to get what you want!

FOSSBilling is not designed to be all things to all people, but we do want it to be as useful and usable as possible. If you have a suggestion for a new feature or an improvement to an existing one then please do submit it. Please be as clear and explicit as you can and provide as much detail as you can, this will make it much easier for the community and maintainers to understand your suggestion and take action.

Before creating enhancement suggestions, please check through the [existing Issues](https://github.com/FOSSBilling/FOSSBilling/issues) and see if somebody has already made the same suggestion. If they have then please don't create a new issue, but instead, add your thoughts and comments to the existing one. 

### Contributing code

The source code is the heart of FOSSBilling, and we are always interested in quality contributions to improve it, squash bugs, and close open issues. Please follow these guidelines to make things easier for yourself and other contributors.

#### What to work on
Check out our upcoming Milestones for an overview of what needs to be done. See the Good first issue label for a list of issues that should be relatively easy to get started with. If there's anything you're unsure of, don't hesitate to ask! All of us were just starting once.

If you're planning to go ahead and work on something, please leave a comment on the relevant issue or create a new one explaining what you are doing. This helps us divide our efforts more sensibly by ensuring that we are not all doing the same thing at the same time.

#### Local development

FOSSBilling and all packages can be developed locally. Instructions on how to do this are provided in [Readme](README.md):

#### Making a pull request

The process described here has several goals:

- Maintain FOSSBilling's quality
- Fix problems that are important to users
- Engage the community in working toward the best possible FOSSBilling
- Enable a sustainable system for FOSSBilling's maintainers to review contributions

Please follow these steps to have your contribution considered by the maintainers:

1. Follow the [style guides](#styleguides)
2. After you submit your pull request, verify that all [status checks](https://help.github.com/articles/about-status-checks/) are passing <details><summary>What if the status checks are failing?</summary>If a status check is failing, and you believe that the failure is unrelated to your change, please leave a comment on the pull request explaining why you believe the failure is unrelated. A maintainer will re-run the status check for you. If we conclude that the failure was a false positive, then we will open an issue to track that problem with our status check suite.</details>

Before a PR can be merged it must pass all of the automated tests and also be reviewed by two maintainers. All of the above requirements must be met before your pull request will be reviewed. Please be aware that the reviewers may ask you to complete additional design work, tests, or other changes before your pull request can be ultimately accepted.

### Writing documentation

Great code is only one-half of any successful project, and great documentation is just as important. The reality is that open source projects can stand or fall based on the quality of their documentation.

The documentation for FOSSBilling is hosted here: [FOSSBilling Docs](https://fossbilling.org/docs)

Documentation is built using [Nextra](https://nextra.site/) from this [GitHub repository](https://github.com/FOSSBilling/docs). You can contribute directly to the repo on GitHub or using the *Edit this page* links on each page of the docs site.

Please try to be thorough and clear when writing directions. Something might seem obvious to you, but do not assume that it is to everybody else. 

### Translating FOSSBilling

We would like FOSSBilling to be available to as many people in as many languages as possible. 

The software is primarily written in English. If you are a native or fluent speaker of another language then we could use your help with the translations.

We use Crowdin to manage translations. You can take a look at the [getting started guide](https://support.crowdin.com/crowdin-intro/), and then get involved in translating at [https://translate.fossbilling.org](https://translate.fossbilling.org).

### Sponsoring the project

If you do not have the time or necessary skills to actively take part in the development or documentation of the project then you can still play a part by making a financial contribution to the project. This could be a one-time contribution or a recurring monthly donation.

You can do this using [GitHub Sponsors](https://github.com/sponsors/FOSSBilling) or on [Open Collective](https://opencollective.com/FOSSBilling). 


## Style Guides

### Commit Messages Style Guide

Please be as clear and descriptive as possible in your commit messages, it makes it much easier for everyone to follow them. 

* Use the present tense ("Add feature" not "Added feature")
* Use the imperative mood ("Move the cursor to..." not "Moves the cursor to...")
* Limit the first line to 72 characters or less
* Reference issues and pull requests liberally after the first line
* When only changing documentation, include `[ci skip]` in the commit title
* When it is appropriate to start the commit message with an applicable emoji:
    * 🎨 when improving the format/structure of the code
    * 🚀 when improving performance
    * 📝 when writing docs
    * 🐛 when fixing a bug
    * 🔒 when dealing with security
    * ⬆️ when upgrading dependencies
    * :sparkles: when it is a new feature
    
### PHP Style Guide

All PHP must adhere to [PSR-12](https://www.php-fig.org/psr/psr-12/).

### Documentation Style Guide

We don't have a formal documentation style guide yet, but we will be developing one soon. In the meantime please take a look at the existing documentation and follow the tone and writing style so that everything stays coherent. 

Nextra uses Markdown and MDX. Please [see their guides](https://nextra.site/docs/guide/markdown) for how to use them if you are not sure. 

## But, I still have a question!

Ask in the [forum](https://forum.fossbilling.org/) or drop a message to the [Discord](https://fossbilling.org/discord) community with a question. Sometimes it takes time to respond; please be patient!

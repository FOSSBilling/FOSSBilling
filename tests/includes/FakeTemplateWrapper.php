<?php
/**
 * Defines a fake template class to mock \Twig_TemplateWrapper.
 *
 * We cannot use getMockBuilder() for this, because the Twig TemplateWrapper
 * class is declared "final" and cannot be mocked.
 *
 *
https://github.rpi.edu/DotCIOweb/test-pantheon-starterkit/blob/107b23d9f231c392e2cd9b4f677f4c1a30e508fa/core/modules/help_topics/tests/src/Unit/HelpTopicTwigTest.php
 *
 */
class FakeTemplateWrapper
{
/**
 * Body text to return from the render() method.
 *
 * @var string
 */
    protected $body;
/**
 * Constructor.
 *
 * @param string $body
 * Body text to return from the render() method.
 */
    public function __construct($body)
    {
        $this->body = $body;
    }
/**
 * Mocks the \Twig_TemplateWrapper render() method.
 *
 * @param array $context
 * (optional) Render context.
 */
    public function render(array $context = [])
    {
        return $this->body;
    }
}
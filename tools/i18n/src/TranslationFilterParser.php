<?php

declare(strict_types=1);

namespace FOSSBilling\Tools\I18n;

use Twig\Error\SyntaxError;
use Twig\ExpressionParser\AbstractExpressionParser;
use Twig\ExpressionParser\Infix\FilterExpressionParser;
use Twig\ExpressionParser\InfixAssociativity;
use Twig\ExpressionParser\InfixExpressionParserInterface;
use Twig\ExpressionParser\PrecedenceChange;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Parser;
use Twig\Token;
use Twig\TokenStream;

/** Preserve argument ambiguity checks before Twig turns named arguments into a map. */
final class TranslationFilterParser extends AbstractExpressionParser implements InfixExpressionParserInterface
{
    private readonly FilterExpressionParser $delegate;

    public function __construct()
    {
        $this->delegate = new FilterExpressionParser();
    }

    public function parse(Parser $parser, AbstractExpression $expr, Token $token): AbstractExpression
    {
        $stream = $parser->getStream();
        if ($expr instanceof ConstantExpression && is_string($expr->getAttribute('value')) && $stream->test(Token::NAME_TYPE, 'trans') && $stream->look()->getValue() === '(') {
            self::checkArguments(clone $stream);
        }

        return $this->delegate->parse($parser, $expr, $token);
    }

    private static function checkArguments(TokenStream $stream): void
    {
        $stream->next();
        $stream->next(); // Filter name and opening parenthesis.
        $bound = [];
        $named = false;
        $index = 0;
        $start = true;
        $depth = 0;
        while (!$stream->isEOF()) {
            $token = $stream->getCurrent();
            $value = $token->getValue();
            if ($depth === 0 && $token->test(Token::PUNCTUATION_TYPE, ')')) {
                break;
            }
            if ($start) {
                if ($value === '...' && !$token->test(Token::STRING_TYPE)) {
                    throw new SyntaxError('argument unpacking prevents resolving the translation source', $token->getLine(), $stream->getSourceContext());
                }
                $name = ['values', 'from'][$index] ?? (string) $index;
                if ($token->test(Token::NAME_TYPE) && in_array($stream->look()->getValue(), [':', '='], true)) {
                    $name = $value;
                    $named = true;
                } elseif ($named) {
                    throw new SyntaxError('positional argument after named argument', $token->getLine(), $stream->getSourceContext());
                }
                if (isset($bound[$name])) {
                    throw new SyntaxError("duplicate argument '$name'", $token->getLine(), $stream->getSourceContext());
                }
                $bound[$name] = true;
                $start = false;
            }
            if (!$token->test(Token::STRING_TYPE)) {
                if (in_array($value, ['(', '[', '{'], true) || $token->test(Token::INTERPOLATION_START_TYPE)) {
                    ++$depth;
                } elseif (in_array($value, [')', ']', '}'], true) || $token->test(Token::INTERPOLATION_END_TYPE)) {
                    --$depth;
                } elseif ($depth === 0 && $token->test(Token::PUNCTUATION_TYPE, ',')) {
                    $start = true;
                    ++$index;
                }
            }
            $stream->next();
        }
    }

    public function getName(): string
    {
        return $this->delegate->getName();
    }

    public function getPrecedence(): int
    {
        return $this->delegate->getPrecedence();
    }

    public function getPrecedenceChange(): ?PrecedenceChange
    {
        return $this->delegate->getPrecedenceChange();
    }

    public function getAssociativity(): InfixAssociativity
    {
        return $this->delegate->getAssociativity();
    }
}

<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Css_Parser
 */

namespace Horde\Css\Parser;

use Exception;
use Horde\Exception\DetailsTrait;
use Horde\Exception\HordeThrowable;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser as SabberwormParser;
use Sabberworm\CSS\Property\Import as SabberwormImport;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\URL as SabberwormUrl;
use Throwable;

/**
 * Modern CSS parser wrapper.
 *
 * Completely encapsulates Sabberworm - consumers never see Sabberworm types.
 * All methods that modify the document return new Parser instances (immutable).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Css_Parser
 */
final class Parser
{
    private Document $document;

    /**
     * Parse CSS string into a parser instance.
     *
     * @param string $css CSS to parse
     * @param Settings|null $settings Parser settings
     * @throws HordeThrowable If CSS cannot be parsed
     */
    public function __construct(string $css, ?Settings $settings = null)
    {
        try {
            $parser = new SabberwormParser($css, $settings ?? Settings::create());
            $this->document = $parser->parse();
        } catch (Exception $e) {
            throw new class ($e->getMessage(), (int) $e->getCode(), $e) extends Exception implements HordeThrowable {
                use DetailsTrait;
            };
        }
    }

    /**
     * Extract all @import statements from the CSS.
     *
     * @return Import[] Array of Import value objects
     */
    public function getImports(): array
    {
        $imports = [];
        foreach ($this->document->getContents() as $element) {
            if ($element instanceof SabberwormImport) {
                $url = $element->getLocation()->getURL()->getString();
                $imports[] = new Import($url);
            }
        }
        return $imports;
    }

    /**
     * Remove all @import statements from the CSS.
     *
     * Returns a new Parser instance with imports removed (immutable).
     *
     * @return self New Parser instance without imports
     */
    public function removeImports(): self
    {
        $newDocument = $this->deepCloneDocument();
        $toRemove = [];
        foreach ($newDocument->getContents() as $element) {
            if ($element instanceof SabberwormImport) {
                $toRemove[] = $element;
            }
        }
        foreach ($toRemove as $element) {
            $newDocument->remove($element);
        }

        return $this->fromDocument($newDocument);
    }

    /**
     * Extract all URL values from CSS rules.
     *
     * Recursively traverses nested value lists to find all url() references.
     *
     * @return Url[] Array of Url value objects
     */
    public function getAllUrls(): array
    {
        $urls = [];
        foreach ($this->document->getAllRuleSets() as $ruleSet) {
            foreach ($ruleSet->getRules() as $rule) {
                $this->extractUrlsFromValue($rule->getValue(), $urls);
            }
        }
        return $urls;
    }

    /**
     * Transform all URL strings in the CSS.
     *
     * Returns a new Parser instance with modified URLs (immutable).
     *
     * @param callable $callback Function that receives URL string, returns modified string
     *                           Signature: callable(string $url): string
     * @return self New Parser instance with modified URLs
     */
    public function modifyUrls(callable $callback): self
    {
        $newDocument = $this->deepCloneDocument();
        foreach ($newDocument->getAllRuleSets() as $ruleSet) {
            foreach ($ruleSet->getRules() as $rule) {
                $this->modifyUrlsInValue($rule->getValue(), $callback);
            }
        }
        return $this->fromDocument($newDocument);
    }

    /**
     * Remove all CSS rules that contain URL values.
     *
     * Security filtering method - removes any rule with url() to prevent
     * external resource loading.
     *
     * Returns a new Parser instance with URL rules removed (immutable).
     *
     * @return self New Parser instance without URL rules
     */
    public function removeUrlRules(): self
    {
        $newDocument = $this->deepCloneDocument();
        foreach ($newDocument->getContents() as $element) {
            if ($this->isRuleContainer($element)) {
                $toRemove = [];
                foreach ($element->getRules() as $rule) {
                    if ($this->valueContainsUrl($rule->getValue())) {
                        $toRemove[] = $rule;
                    }
                }
                foreach ($toRemove as $rule) {
                    $element->removeRule($rule);
                }
            }
        }
        return $this->fromDocument($newDocument);
    }

    /**
     * Remove CSS rules by property name.
     *
     * Security filtering method - removes specific CSS properties
     * (e.g., 'cursor', 'behavior').
     *
     * Returns a new Parser instance with named rules removed (immutable).
     *
     * @param string ...$names CSS property names to remove
     * @return self New Parser instance without named rules
     */
    public function removeRulesByName(string ...$names): self
    {
        $newDocument = $this->deepCloneDocument();
        foreach ($newDocument->getContents() as $element) {
            if ($this->isRuleContainer($element)) {
                $toRemove = [];
                foreach ($element->getRules() as $rule) {
                    if (in_array($this->getRulePropertyName($rule), $names, true)) {
                        $toRemove[] = $rule;
                    }
                }
                foreach ($toRemove as $rule) {
                    $element->removeRule($rule);
                }
            }
        }
        return $this->fromDocument($newDocument);
    }

    /**
     * Keep ONLY CSS rules that contain URL values (inverse of removeUrlRules).
     *
     * Returns a new Parser instance containing only rules with url() references.
     * All other rules are removed. Useful for extracting potentially dangerous
     * CSS for optional user loading.
     *
     * Returns a new Parser instance with only URL rules (immutable).
     *
     * @return self New Parser instance containing only URL rules
     */
    public function keepOnlyUrlRules(): self
    {
        $newDocument = $this->deepCloneDocument();
        foreach ($newDocument->getContents() as $element) {
            if ($this->isRuleContainer($element)) {
                $toRemove = [];
                foreach ($element->getRules() as $rule) {
                    // Remove rules that DON'T contain URLs
                    if (!$this->valueContainsUrl($rule->getValue())) {
                        $toRemove[] = $rule;
                    }
                }
                foreach ($toRemove as $rule) {
                    $element->removeRule($rule);
                }
            }
        }
        return $this->fromDocument($newDocument);
    }

    /**
     * Keep ONLY CSS rules matching specific property names (inverse of removeRulesByName).
     *
     * Returns a new Parser instance containing only the named properties.
     * All other rules are removed. Useful for extracting specific CSS
     * properties for optional user loading.
     *
     * Returns a new Parser instance with only named rules (immutable).
     *
     * @param string ...$names CSS property names to keep
     * @return self New Parser instance containing only named rules
     */
    public function keepOnlyRulesByName(string ...$names): self
    {
        $newDocument = $this->deepCloneDocument();
        foreach ($newDocument->getContents() as $element) {
            if ($this->isRuleContainer($element)) {
                $toRemove = [];
                foreach ($element->getRules() as $rule) {
                    // Remove rules that DON'T match the names
                    if (!in_array($this->getRulePropertyName($rule), $names, true)) {
                        $toRemove[] = $rule;
                    }
                }
                foreach ($toRemove as $rule) {
                    $element->removeRule($rule);
                }
            }
        }
        return $this->fromDocument($newDocument);
    }

    /**
     * Keep ONLY @import statements and rules with URLs or specific names.
     *
     * Combined method for extracting "dangerous" CSS that might be blocked.
     * Keeps imports, URL rules, and optionally named rules (e.g., 'cursor').
     * All other content is removed.
     *
     * Returns a new Parser instance with only dangerous CSS (immutable).
     *
     * @param string ...$ruleNames Optional CSS property names to also keep
     * @return self New Parser instance containing only imports, URLs, and named rules
     */
    public function keepOnlyDangerousCss(string ...$ruleNames): self
    {
        $newDocument = $this->deepCloneDocument();

        // Remove all non-import top-level elements except RuleSets/DeclarationBlocks
        $toRemoveTopLevel = [];
        foreach ($newDocument->getContents() as $element) {
            if (!($element instanceof SabberwormImport) && !$this->isRuleContainer($element)) {
                $toRemoveTopLevel[] = $element;
            }
        }
        foreach ($toRemoveTopLevel as $element) {
            $newDocument->remove($element);
        }

        // In RuleSets/DeclarationBlocks, keep only URL rules and named rules
        foreach ($newDocument->getContents() as $element) {
            if ($this->isRuleContainer($element)) {
                $toRemove = [];
                foreach ($element->getRules() as $rule) {
                    $keepRule = false;

                    // Keep if contains URL
                    if ($this->valueContainsUrl($rule->getValue())) {
                        $keepRule = true;
                    }

                    // Keep if matches named rules
                    if (!empty($ruleNames) && in_array($this->getRulePropertyName($rule), $ruleNames, true)) {
                        $keepRule = true;
                    }

                    // Remove safe rules
                    if (!$keepRule) {
                        $toRemove[] = $rule;
                    }
                }
                foreach ($toRemove as $rule) {
                    $element->removeRule($rule);
                }
            }
        }

        return $this->fromDocument($newDocument);
    }

    /**
     * Extract CSS rules matching specific selectors.
     *
     * Returns serialized CSS rules (property:value pairs) for declaration
     * blocks that match any of the provided selectors.
     *
     * @param array<string> $selectors CSS selectors to match (e.g., ['body', '.header'])
     * @return string Serialized CSS rules matching the selectors
     */
    public function getRulesBySelectors(array $selectors): string
    {
        $output = '';
        foreach ($this->document->getContents() as $element) {
            if ($element instanceof DeclarationBlock) {
                $elementSelectors = array_map(
                    fn($sel) => $this->getSelectorString($sel),
                    $element->getSelectors()
                );
                if (array_intersect($selectors, $elementSelectors)) {
                    $rules = array_map(
                        fn($rule) => $this->getRuleString($rule),
                        $this->getDeclarationBlockRules($element)
                    );
                    $output .= implode('', $rules);
                }
            }
        }
        return $output;
    }

    /**
     * Compress CSS to minified format.
     *
     * @return string Minified CSS string
     */
    public function compress(): string
    {
        // Use legacy compress format for BC compatibility
        return str_replace(
            ["\n", ' {', ': ', ';}', ', '],
            ['', '{', ':', '}', ','],
            $this->document->render()
        );
    }

    /**
     * Render CSS to string.
     *
     * @return string Formatted CSS string
     */
    public function render(): string
    {
        return $this->document->render();
    }

    /**
     * Convert to string (renders CSS).
     *
     * @return string Formatted CSS string
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Internal: Extract URLs from a rule value recursively.
     *
     * @param mixed $value Rule value to examine
     * @param array<Url> &$urls Array to append found URLs to
     */
    private function extractUrlsFromValue($value, array &$urls): void
    {
        if ($value instanceof SabberwormUrl) {
            $urlString = $value->getURL()->getString();
            $urls[] = new Url($urlString);
        } elseif ($value instanceof RuleValueList) {
            foreach ($value->getListComponents() as $component) {
                $this->extractUrlsFromValue($component, $urls);
            }
        }
    }

    /**
     * Internal: Modify URLs in a rule value recursively.
     *
     * @param mixed $value Rule value to modify
     * @param callable $callback Transformation function
     */
    private function modifyUrlsInValue($value, callable $callback): void
    {
        if ($value instanceof SabberwormUrl) {
            $cssString = $value->getURL();
            $oldUrl = $cssString->getString();
            $newUrl = $callback($oldUrl);
            $cssString->setString($newUrl);
        } elseif ($value instanceof RuleValueList) {
            foreach ($value->getListComponents() as $component) {
                $this->modifyUrlsInValue($component, $callback);
            }
        }
    }

    /**
     * Internal: Check if a rule value contains any URLs.
     *
     * @param mixed $value Rule value to check
     * @return bool True if value contains URLs
     */
    private function valueContainsUrl($value): bool
    {
        if ($value instanceof SabberwormUrl) {
            return true;
        }
        if ($value instanceof RuleValueList) {
            foreach ($value->getListComponents() as $component) {
                if ($this->valueContainsUrl($component)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Internal: Create Parser from existing Document.
     *
     * Used for immutability pattern - returns new instance with cloned document.
     *
     * @param Document $document Sabberworm document
     * @return self New Parser instance
     */
    private function fromDocument(Document $document): self
    {
        // Create new instance by re-rendering the document
        // This ensures complete isolation between instances
        $instance = new self($document->render());
        return $instance;
    }

    /**
     * Internal: Deep clone the document for immutability.
     *
     * PHP's clone doesn't deep-clone objects, so we re-parse the CSS
     * to create a truly independent copy.
     *
     * @return Document New document instance
     */
    private function deepCloneDocument(): Document
    {
        // Re-parse to get a truly independent copy
        $css = $this->document->render();
        $parser = new SabberwormParser($css, Settings::create());
        return $parser->parse();
    }

    /**
     * Internal: Detect if Sabberworm 9.x is installed.
     *
     * Uses class existence check for reliable version detection.
     * Property\Declaration only exists in Sabberworm 9.2+.
     *
     * @return bool True if Sabberworm 9.2+, false if 8.x
     */
    private function isSabberworm9x(): bool
    {
        static $is9x = null;
        if ($is9x === null) {
            $is9x = class_exists('Sabberworm\\CSS\\Property\\Declaration');
        }
        return $is9x;
    }

    /**
     * Internal: Get CSS property name from rule object (version-agnostic).
     *
     * Handles both Sabberworm 8.x (getRule) and 9.2+ (getPropertyName).
     * Uses runtime method detection for maximum compatibility.
     *
     * @param mixed $rule Rule/Declaration object from Sabberworm
     * @return string CSS property name (e.g., 'color', 'cursor')
     */
    private function getRulePropertyName($rule): string
    {
        if (method_exists($rule, 'getPropertyName')) {
            return $rule->getPropertyName(); // Sabberworm 9.2+
        }
        return $rule->getRule(); // Sabberworm 8.x
    }

    /**
     * Internal: Get rules from DeclarationBlock (version-agnostic).
     *
     * Handles structural change in Sabberworm 9.0 where DeclarationBlock
     * no longer extends RuleSet and contains it as a property instead.
     *
     * @param DeclarationBlock $block Declaration block
     * @return array<mixed> Array of Rule/Declaration objects
     */
    private function getDeclarationBlockRules(DeclarationBlock $block): array
    {
        // In 9.0+, DeclarationBlock contains RuleSet as property
        if (method_exists($block, 'getRuleSet')) {
            return $block->getRuleSet()->getRules();
        }
        // In 8.x, DeclarationBlock extends RuleSet directly
        return $block->getRules();
    }

    /**
     * Internal: Check if element is a RuleSet or DeclarationBlock (version-agnostic).
     *
     * In Sabberworm 8.x, DeclarationBlock extends RuleSet.
     * In Sabberworm 9.0+, DeclarationBlock does NOT extend RuleSet.
     * This method handles both cases for filtering operations.
     *
     * @param mixed $element Element from document
     * @return bool True if element has rules that can be filtered
     */
    private function isRuleContainer($element): bool
    {
        return ($element instanceof RuleSet) || ($element instanceof DeclarationBlock);
    }

    /**
     * Internal: Get selector string (version-agnostic).
     *
     * In Sabberworm 8.x, Selector has __toString().
     * In Sabberworm 9.x, Selector doesn't have __toString(), use getSelector().
     *
     * @param mixed $selector Selector object from Sabberworm
     * @return string Selector string (e.g., 'body', '.header')
     */
    private function getSelectorString($selector): string
    {
        // In 9.x, Selector no longer has __toString()
        if (method_exists($selector, 'getSelector')) {
            return $selector->getSelector();
        }
        // In 8.x, use __toString()
        return (string) $selector;
    }

    /**
     * Internal: Get rule string (version-agnostic).
     *
     * In Sabberworm 8.x, Rule has __toString().
     * In Sabberworm 9.x, Declaration requires OutputFormat for render().
     *
     * @param mixed $rule Rule/Declaration object from Sabberworm
     * @return string Rule string (e.g., 'color:red;')
     */
    private function getRuleString($rule): string
    {
        // In 9.x, render() requires OutputFormat parameter
        if (method_exists($rule, 'render')) {
            try {
                // Try with OutputFormat (9.x)
                return $rule->render(OutputFormat::createCompact());
            } catch (Throwable $e) {
                // Fall back to render() without args (8.x)
                return $rule->render();
            }
        }
        // Fallback to string cast
        return (string) $rule;
    }
}

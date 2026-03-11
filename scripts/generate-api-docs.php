<?php

	declare(strict_types=1);

	/**
	 * SharkordPHP API Documentation Generator
	 *
	 * Uses PHP's Reflection API to introspect every public class, interface, and
	 * trait in src/ and emits a Markdown file per class into docs-src/api/.
	 * Also writes docs-src/api/sidebar.json for VitePress to consume.
	 *
	 * Prerequisites:
	 *   composer install   (dependencies must be available for class loading)
	 *
	 * Usage:
	 *   php scripts/generate-api-docs.php
	 */

	require __DIR__ . '/../vendor/autoload.php';

	// ─── Data Structures ──────────────────────────────────────────────────────

	/**
	 * Parsed contents of a PHPDoc tag.
	 */
	final class DocTag {

		public function __construct(
			public readonly string $name,
			public readonly string $value,
		) {}

	}

	/**
	 * Parsed @param tag.
	 */
	final class DocParam {

		public function __construct(
			public readonly string $type,
			public readonly string $varName,
			public readonly string $description,
		) {}

	}

	// ─── Docblock Parser ──────────────────────────────────────────────────────

	/**
	 * Strips the /** … *\/ wrapper and leading asterisks from a raw docblock
	 * string, returning an array of cleaned lines.
	 *
	 * @param string $raw Raw PHPDoc string including delimiters.
	 * @return string[]
	 */
	function cleanDocblockLines(string $raw): array {

		$lines = explode("\n", $raw);

		return array_map(
			static fn(string $line): string => (string) preg_replace('/^\s*\*\s?/', '', trim($line)),
			$lines
		);

	}

	/**
	 * Parses a raw PHPDoc block into its summary, long description, and tags.
	 *
	 * @param string $raw Raw docblock string.
	 * @return array{summary: string, description: string, tags: DocTag[]}
	 */
	function parseDocblock(string $raw): array {

		$lines = cleanDocblockLines($raw);

		// Drop the /** and */ lines.
		array_shift($lines);
		array_pop($lines);

		$summary      = '';
		$description  = '';
		/** @var DocTag[] */
		$tags         = [];
		$descLines    = [];

		$inTag        = false;
		$currentTag   = '';
		$currentValue = '';

		foreach ($lines as $line) {

			if (str_starts_with(trim($line), '@')) {

				if ($inTag) {
					$tags[] = new DocTag($currentTag, trim($currentValue));
				}

				$inTag    = true;
				$spacePos = strpos($line, ' ');

				if ($spacePos === false) {
					$currentTag   = ltrim($line, '@');
					$currentValue = '';
				} else {
					$currentTag   = ltrim(substr($line, 0, $spacePos), '@');
					$currentValue = substr($line, $spacePos + 1);
				}

			} elseif ($inTag) {
				$currentValue .= "\n" . $line;
			} else {
				$descLines[] = $line;
			}

		}

		if ($inTag) {
			$tags[] = new DocTag($currentTag, trim($currentValue));
		}

		// First non-empty line is the summary.
		$nonEmpty = array_values(array_filter($descLines, static fn(string $l): bool => trim($l) !== ''));

		if (!empty($nonEmpty)) {
			$summary = $nonEmpty[0];
			$rest    = array_slice($descLines, array_search($nonEmpty[0], $descLines, true) + 1);

			while (!empty($rest) && trim($rest[0]) === '') {
				array_shift($rest);
			}

			$description = implode("\n", $rest);
		}

		return [
			'summary'     => $summary,
			'description' => $description,
			'tags'        => $tags,
		];

	}

	/**
	 * Extracts all @param tags from a tag list.
	 *
	 * @param DocTag[] $tags
	 * @return DocParam[]
	 */
	function extractParamTags(array $tags): array {

		$params = [];

		foreach ($tags as $tag) {

			if ($tag->name !== 'param') {
				continue;
			}

			$parts    = preg_split('/\s+/', $tag->value, 3);
			$params[] = new DocParam(
				type:        $parts[0] ?? '',
				varName:     $parts[1] ?? '',
				description: isset($parts[2]) ? trim($parts[2]) : '',
			);

		}

		return $params;

	}

	/**
	 * Extracts the @return tag from a tag list.
	 *
	 * @param  DocTag[] $tags
	 * @return array{type: string, description: string}
	 */
	function extractReturnTag(array $tags): array {

		foreach ($tags as $tag) {

			if ($tag->name !== 'return') {
				continue;
			}

			$parts = preg_split('/\s+/', $tag->value, 2);

			return [
				'type'        => $parts[0] ?? '',
				'description' => isset($parts[1]) ? trim($parts[1]) : '',
			];

		}

		return ['type' => '', 'description' => ''];

	}

	/**
	 * Extracts all @throws tags from a tag list.
	 *
	 * @param  DocTag[] $tags
	 * @return array<array{type: string, description: string}>
	 */
	function extractThrowsTags(array $tags): array {

		$throws = [];

		foreach ($tags as $tag) {

			if ($tag->name !== 'throws') {
				continue;
			}

			$parts    = preg_split('/\s+/', $tag->value, 2);
			$throws[] = [
				'type'        => $parts[0] ?? '',
				'description' => isset($parts[1]) ? trim($parts[1]) : '',
			];

		}

		return $throws;

	}

	/**
	 * Extracts the @example tag body from a tag list.
	 *
	 * @param DocTag[] $tags
	 * @return string
	 */
	function extractExampleTag(array $tags): string {

		foreach ($tags as $tag) {
			if ($tag->name === 'example') {
				return $tag->value;
			}
		}

		return '';

	}

	// ─── Reflection Helpers ───────────────────────────────────────────────────

	/**
	 * Converts a ReflectionType to a human-readable string, stripping the
	 * leading backslash from fully-qualified class names.
	 *
	 * @param ReflectionType|null $type
	 * @return string
	 */
	function formatReflectionType(?ReflectionType $type): string {

		if ($type === null) {
			return '';
		}

		return ltrim((string) $type, '\\');

	}

	/**
	 * Builds a method signature string from a ReflectionMethod.
	 *
	 * @param ReflectionMethod $method
	 * @return string
	 */
	function buildSignature(ReflectionMethod $method): string {

		$parts = [];

		if ($method->isAbstract() && !$method->getDeclaringClass()->isInterface()) {
			$parts[] = 'abstract';
		}

		if ($method->isStatic()) {
			$parts[] = 'static';
		}

		$parts[] = 'public function ' . $method->getName() . '(';

		$params = [];

		foreach ($method->getParameters() as $param) {

			$segment = '';

			$type = formatReflectionType($param->getType());

			if ($type !== '') {
				$segment .= $type . ' ';
			}

			if ($param->isVariadic()) {
				$segment .= '...';
			}

			$segment .= '$' . $param->getName();

			if ($param->isDefaultValueAvailable()) {
				$default   = $param->getDefaultValue();
				$segment  .= ' = ' . formatDefaultValue($default);
			} elseif ($param->isOptional() && $param->allowsNull()) {
				$segment .= ' = null';
			}

			$params[] = $segment;

		}

		$parts[] = implode(', ', $params) . ')';

		$returnType = formatReflectionType($method->getReturnType());

		if ($returnType !== '') {
			$parts[] = ': ' . $returnType;
		}

		return implode('', $parts);

	}

	/**
	 * Converts a PHP default value to its source-code representation.
	 *
	 * @param mixed $value
	 * @return string
	 */
	function formatDefaultValue(mixed $value): string {

		return match (true) {
			$value === null  => 'null',
			$value === true  => 'true',
			$value === false => 'false',
			is_string($value) => "'" . addcslashes($value, "'\\") . "'",
			is_array($value)  => '[]',
			default           => (string) $value,
		};

	}

	// ─── Markdown Generator ───────────────────────────────────────────────────

	/**
	 * Wraps a type string in backticks for inline Markdown code formatting.
	 *
	 * @param string $type
	 * @return string
	 */
	function mdType(string $type): string {
		return $type !== '' ? '`' . $type . '`' : '';
	}

	/**
	 * Generates a full Markdown document for a reflected class or interface.
	 *
	 * @param ReflectionClass<object> $ref
	 * @return string
	 */
	function generateMarkdown(ReflectionClass $ref): string {

		$md = [];

		$classDoc    = $ref->getDocComment();
		$classParsed = $classDoc !== false ? parseDocblock($classDoc) : ['summary' => '', 'description' => '', 'tags' => []];

		$classType = match (true) {
			$ref->isInterface() => 'Interface',
			$ref->isTrait()     => 'Trait',
			$ref->isEnum()      => 'Enum',
			$ref->isAbstract()  => 'Abstract Class',
			default             => 'Class',
		};

		// ── Title ──────────────────────────────────────────────────────────

		$md[] = '# ' . $ref->getShortName();
		$md[] = '';
		$md[] = '**' . $classType . '** `' . $ref->getName() . '`';
		$md[] = '';

		if ($classParsed['summary'] !== '') {
			$md[] = $classParsed['summary'];
			$md[] = '';
		}

		if ($classParsed['description'] !== '') {
			$md[] = $classParsed['description'];
			$md[] = '';
		}

		$parent = $ref->getParentClass();

		if ($parent !== false) {
			$md[] = '**Extends:** `' . $parent->getName() . '`';
			$md[] = '';
		}

		$interfaces = $ref->getInterfaceNames();

		if (!empty($interfaces)) {
			$md[] = '**Implements:** ' . implode(', ', array_map(static fn(string $i): string => '`' . $i . '`', $interfaces));
			$md[] = '';
		}

		// ── Properties ─────────────────────────────────────────────────────

		$properties = array_filter(
			$ref->getProperties(ReflectionProperty::IS_PUBLIC),
			static fn(ReflectionProperty $p): bool => $p->getDeclaringClass()->getName() === $ref->getName()
		);

		if (!empty($properties)) {

			$md[] = '## Properties';
			$md[] = '';

			foreach ($properties as $prop) {

				$readonly = $prop->isReadOnly() ? ' `readonly`' : '';
				$static   = $prop->isStatic() ? ' `static`' : '';
				$md[]     = '### `$' . $prop->getName() . '`' . $readonly . $static;
				$md[]     = '';

				$type = formatReflectionType($prop->getType());

				if ($type !== '') {
					$md[] = '**Type:** ' . mdType($type);
					$md[] = '';
				}

				$propDoc = $prop->getDocComment();

				if ($propDoc !== false) {
					$parsed = parseDocblock($propDoc);

					// @var tags carry the type description
					$varDescription = '';
					foreach ($parsed['tags'] as $tag) {
						if ($tag->name === 'var') {
							$parts          = preg_split('/\s+/', $tag->value, 3);
							$varDescription = isset($parts[2]) ? trim($parts[2]) : (isset($parts[1]) ? trim($parts[1]) : '');
						}
					}

					$summary = $parsed['summary'] !== '' ? $parsed['summary'] : $varDescription;

					if ($summary !== '') {
						$md[] = $summary;
						$md[] = '';
					}
				}

			}

		}

		// ── Methods ────────────────────────────────────────────────────────

		$methods = array_filter(
			$ref->getMethods(ReflectionMethod::IS_PUBLIC),
			static fn(ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === $ref->getName()
		);

		if (!empty($methods)) {

			$md[] = '## Methods';
			$md[] = '';

			foreach ($methods as $method) {

				$md[] = '### `' . $method->getName() . '()`';
				$md[] = '';

				$methodDoc    = $method->getDocComment();
				$methodParsed = $methodDoc !== false
					? parseDocblock($methodDoc)
					: ['summary' => '', 'description' => '', 'tags' => []];

				// Deprecated notice
				foreach ($methodParsed['tags'] as $tag) {
					if ($tag->name === 'deprecated') {
						$note = $tag->value !== '' ? ' ' . $tag->value : '';
						$md[] = '::: warning Deprecated' . $note;
						$md[] = ':::';
						$md[] = '';
					}
				}

				if ($methodParsed['summary'] !== '') {
					$md[] = $methodParsed['summary'];
					$md[] = '';
				}

				if ($methodParsed['description'] !== '') {
					$md[] = $methodParsed['description'];
					$md[] = '';
				}

				// Signature
				$md[] = '```php';
				$md[] = buildSignature($method);
				$md[] = '```';
				$md[] = '';

				// Parameter table
				$paramTags = extractParamTags($methodParsed['tags']);

				if (!empty($paramTags)) {

					$md[] = '**Parameters**';
					$md[] = '';
					$md[] = '| Name | Type | Description |';
					$md[] = '|------|------|-------------|';

					foreach ($paramTags as $param) {
						$desc = str_replace('|', '\|', $param->description);
						$md[] = '| `' . $param->varName . '` | ' . mdType($param->type) . ' | ' . $desc . ' |';
					}

					$md[] = '';

				}

				// Return
				$returnTag  = extractReturnTag($methodParsed['tags']);
				$returnType = $returnTag['type'] !== '' ? $returnTag['type'] : formatReflectionType($method->getReturnType());

				if ($returnType !== '' && $returnType !== 'void') {
					$line = '**Returns:** ' . mdType($returnType);
					if ($returnTag['description'] !== '') {
						$line .= ' — ' . $returnTag['description'];
					}
					$md[] = $line;
					$md[] = '';
				}

				// Throws
				$throws = extractThrowsTags($methodParsed['tags']);

				if (!empty($throws)) {

					$md[] = '**Throws**';
					$md[] = '';

					foreach ($throws as $throw) {
						$line = '- ' . mdType($throw['type']);
						if ($throw['description'] !== '') {
							$line .= ' — ' . $throw['description'];
						}
						$md[] = $line;
					}

					$md[] = '';

				}

				// Example
				$example = extractExampleTag($methodParsed['tags']);

				if ($example !== '') {

					$md[]    = '**Example**';
					$md[]    = '';
					$example = trim($example);

					if (!str_starts_with($example, '```')) {
						$md[] = '```php';
						$md[] = $example;
						$md[] = '```';
					} else {
						$md[] = $example;
					}

					$md[] = '';

				}

				$md[] = '---';
				$md[] = '';

			}

		}

		return implode("\n", $md);

	}

	// ─── Sidebar Builder ──────────────────────────────────────────────────────

	/**
	 * Builds the VitePress sidebar array from a list of reflected classes,
	 * grouped by sub-namespace.
	 *
	 * @param ReflectionClass<object>[] $classes
	 * @return array<mixed>
	 */
	function buildSidebar(array $classes): array {

		$groups = [];

		foreach ($classes as $ref) {

			$ns       = $ref->getNamespaceName();
			$relative = (string) preg_replace('/^Sharkord\\\\?/', '', $ns);
			$group    = $relative !== '' ? str_replace('\\', ' / ', $relative) : 'Core';
			$link     = '/api/' . ($relative !== '' ? str_replace('\\', '/', $relative) . '/' : '') . $ref->getShortName();

			$groups[$group][] = [
				'text' => $ref->getShortName(),
				'link' => $link,
			];

		}

		uksort($groups, static function (string $a, string $b): int {
			if ($a === 'Core') return -1;
			if ($b === 'Core') return 1;
			return strcmp($a, $b);
		});

		$sidebar = [];

		foreach ($groups as $groupName => $items) {
			$sidebar[] = [
				'text'      => $groupName,
				'collapsed' => false,
				'items'     => $items,
			];
		}

		return $sidebar;

	}

	// ─── Entry Point ──────────────────────────────────────────────────────────

	$srcDir    = __DIR__ . '/../src';
	$outputDir = __DIR__ . '/../docs-src/api';

	if (!is_dir($srcDir)) {
		fwrite(STDERR, "Error: src/ directory not found at '{$srcDir}'.\n");
		exit(1);
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
	);

	/** @var ReflectionClass<object>[] */
	$classes = [];

	/** @var SplFileInfo $file */
	foreach ($iterator as $file) {

		if ($file->getExtension() !== 'php') {
			continue;
		}

		$source = file_get_contents($file->getRealPath());

		if ($source === false) {
			continue;
		}

		// Extract FQN with a lightweight regex — Reflection handles everything else.
		preg_match('/^namespace\s+([\w\\\\]+);/m', $source, $nsMatch);
		preg_match('/(?:^|\s)(?:abstract\s+)?(?:final\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $source, $classMatch);

		if (empty($nsMatch[1]) || empty($classMatch[1])) {
			continue;
		}

		$fqn = $nsMatch[1] . '\\' . $classMatch[1];

		try {
			$ref = new ReflectionClass($fqn);
		} catch (ReflectionException $e) {
			fwrite(STDERR, "Warning: Could not reflect '{$fqn}': {$e->getMessage()}\n");
			continue;
		}

		// Skip anonymous classes and those not belonging to this package.
		if ($ref->isAnonymous() || !str_starts_with($ref->getName(), 'Sharkord\\')) {
			continue;
		}

		// Determine output path mirroring namespace structure.
		$relative      = (string) preg_replace('/^Sharkord\\\\?/', '', $ref->getNamespaceName());
		$classOutDir   = $outputDir . ($relative !== '' ? '/' . str_replace('\\', '/', $relative) : '');

		if (!is_dir($classOutDir)) {
			mkdir($classOutDir, 0755, true);
		}

		$outFile  = $classOutDir . '/' . $ref->getShortName() . '.md';
		$markdown = generateMarkdown($ref);
		file_put_contents($outFile, $markdown);

		echo "Generated: {$outFile}\n";

		$classes[] = $ref;

	}

	// Sort alphabetically for consistent sidebar ordering.
	usort($classes, static fn(ReflectionClass $a, ReflectionClass $b): int => strcmp($a->getName(), $b->getName()));

	// Write sidebar JSON.
	$sidebar     = buildSidebar($classes);
	$sidebarFile = $outputDir . '/sidebar.json';
	file_put_contents($sidebarFile, json_encode($sidebar, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	echo "Generated: {$sidebarFile}\n";

	echo "\nDone. Generated " . count($classes) . " documentation file(s).\n";

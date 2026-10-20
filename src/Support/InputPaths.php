<?php

namespace DVRTech\SchemaTools\Support;

class InputPaths
{
    /**
     * Expand globs, comma-separated lists, and literal file paths.
     *
     * @param array<int, string> $arguments
     * @return array<int, string>
     */
    public static function expand(array $arguments): array
    {
        $paths = [];

        foreach ($arguments as $argument) {
            foreach (self::expandOne(trim((string) $argument)) as $path) {
                $paths[] = $path;
            }
        }

        $paths = array_values(array_unique($paths));
        if ($paths === []) {
            throw new \InvalidArgumentException('No input files were provided.');
        }

        return $paths;
    }

    /**
     * @return array<int, string>
     */
    private static function expandOne(string $argument): array
    {
        if ($argument === '') {
            return [];
        }

        if (preg_match('/[*?\[]/', $argument) === 1) {
            $matches = glob($argument) ?: [];
            $files = [];
            foreach ($matches as $match) {
                if (is_file($match)) {
                    $files[] = $match;
                }
            }
            sort($files);
            if ($files === []) {
                throw new \InvalidArgumentException("No files matched {$argument}");
            }

            return $files;
        }

        if (is_file($argument)) {
            return [$argument];
        }

        if (str_contains($argument, ',')) {
            $paths = [];
            foreach (explode(',', $argument) as $part) {
                foreach (self::expandOne(trim($part)) as $path) {
                    $paths[] = $path;
                }
            }

            return $paths;
        }

        throw new \InvalidArgumentException("File not found: {$argument}");
    }
}

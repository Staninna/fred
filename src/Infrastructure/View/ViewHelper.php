<?php

declare(strict_types=1);

namespace Fred\Infrastructure\View;

use function implode;

/**
 * Helper functions for view rendering.
 * Provides utilities for building common view components and attributes.
 */
final class ViewHelper
{
    /**
     * Builds an aria-describedby attribute string from message target IDs.
     * Returns empty string if no targets are provided.
     *
     * @param string[] $messageTargets Array of element IDs that describe the form
     */
    public static function buildAriaDescribedBy(array $messageTargets): string
    {
        if ($messageTargets === []) {
            return '';
        }

        return ' aria-describedby="' . implode(' ', $messageTargets) . '"';
    }

    /**
     * Collects message target IDs for aria-describedby based on error/success states.
     *
     * @param bool $hasErrors Whether there are error messages
     * @param bool $hasSuccess Whether there is a success message
     * @param string $idPrefix The prefix for error/success element IDs (e.g. 'login-form')
     * @return string[] Array of element IDs
     */
    public static function collectMessageTargets(bool $hasErrors, bool $hasSuccess, string $idPrefix): array
    {
        $targets = [];

        if ($hasErrors) {
            $targets[] = $idPrefix . '-errors';
        }

        if ($hasSuccess) {
            $targets[] = $idPrefix . '-success';
        }

        return $targets;
    }

    /**
     * Builds a complete aria-describedby attribute with collected message targets.
     *
     * @param bool $hasErrors Whether there are error messages
     * @param bool $hasSuccess Whether there is a success message
     * @param string $idPrefix The prefix for error/success element IDs
     */
    public static function buildMessageAria(bool $hasErrors, bool $hasSuccess, string $idPrefix): string
    {
        $targets = self::collectMessageTargets($hasErrors, $hasSuccess, $idPrefix);

        return self::buildAriaDescribedBy($targets);
    }
}

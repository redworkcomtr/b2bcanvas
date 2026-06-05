<?php

namespace App\Support;

final class NotificationEventCatalog
{
    public const ORDER_SHIPPED = 'ORDER_SHIPPED';
    public const ORDER_ACTION_NEEDED = 'ORDER_ACTION_NEEDED';
    public const ORDER_ISSUE_COMMENT_ADDED = 'ORDER_ISSUE_COMMENT_ADDED';
    public const ORDER_VALIDATION_FAILED = 'ORDER_VALIDATION_FAILED';
    public const ORDER_PAYMENT_COMPLETED = 'ORDER_PAYMENT_COMPLETED';

    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            self::ORDER_SHIPPED => 'Order shipped',
            self::ORDER_ACTION_NEEDED => 'Order action needed',
            self::ORDER_ISSUE_COMMENT_ADDED => 'Order issue comment added',
            self::ORDER_VALIDATION_FAILED => 'Order validation failed',
            self::ORDER_PAYMENT_COMPLETED => 'Order payment completed',
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $event): string
    {
        return self::all()[$event] ?? $event;
    }
}

<?php

namespace App\Services;

use App\Models\NotificationSubscription;

class NotificationTemplateService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function compose(string $event, array $payload): array
    {
        return match ($event) {
            'ORDER_SHIPPED' => $this->composeOrderShipped($payload),
            'ORDER_ACTION_NEEDED' => $this->composeOrderActionNeeded($payload),
            'ORDER_ISSUE_COMMENT_ADDED' => $this->composeIssueCommentAdded($payload),
            'ORDER_VALIDATION_FAILED' => $this->composeOrderValidationFailed($payload),
            'ORDER_PAYMENT_COMPLETED' => $this->composePaymentCompleted($payload),
            default => $this->composeDefault($event, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function withUnsubscribe(array $composition, NotificationSubscription $subscription, string $unsubscribeUrl): array
    {
        $bodyHtmlTemplate = $composition['body_html'] ?? $composition['bodyHtml'] ?? '';
        $bodyTextTemplate = $composition['body_text'] ?? $composition['bodyText'] ?? '';

        $bodyHtml = str_replace(
            ['{{UNSUBSCRIBE_LINK}}'],
            [$unsubscribeUrl],
            $bodyHtmlTemplate,
        );

        $bodyText = str_replace(
            ['{{UNSUBSCRIBE_LINK}}'],
            [$unsubscribeUrl],
            $bodyTextTemplate,
        );

        return [
            ...$composition,
            'recipient_name' => $subscription->user?->name ?? $subscription->email,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'unsubscribe_url' => $unsubscribeUrl,
        ];
    }

    /** @return array<string, string> */
    private function composeOrderShipped(array $payload): array
    {
        $orderNumber = (string) ($payload['order_number'] ?? 'N/A');
        $subject = "Order shipped: {$orderNumber}";
        $bodyHtml = implode("\n", [
            '<h1>Order shipped</h1>',
            '<p>Your order <strong>'.$orderNumber.'</strong> has been marked as shipped.</p>',
            '<p>Tracking number: '.(string) ($payload['tracking_number'] ?? 'Pending').'</p>',
            '<p>Service: '.(string) ($payload['shipping_service'] ?? 'N/A').'</p>',
            '<p><a href="'.(string) ($payload['tracking_url'] ?? '#').'">Track package</a></p>',
            '<p>For email preferences, click {{UNSUBSCRIBE_LINK}}</p>',
        ]);

        $bodyText = "Order shipped\nOrder: {$orderNumber}\nTracking: ".($payload['tracking_number'] ?? 'Pending');

        return compact('subject', 'bodyHtml', 'bodyText');
    }

    /** @return array<string, string> */
    private function composeOrderActionNeeded(array $payload): array
    {
        $orderNumber = (string) ($payload['order_number'] ?? 'N/A');
        $reason = (string) ($payload['reason'] ?? 'An action is required before fulfillment can continue.');
        $subject = "Action needed for order {$orderNumber}";

        $bodyHtml = implode("\n", [
            '<h1>Order action required</h1>',
            '<p>Order <strong>'.$orderNumber.'</strong> requires immediate attention.</p>',
            '<p>'.htmlspecialchars($reason).'</p>',
            '<p>Open the portal to review required actions.</p>',
            '<p>For email preferences, click {{UNSUBSCRIBE_LINK}}</p>',
        ]);

        $bodyText = "Order action required\nOrder: {$orderNumber}\nReason: {$reason}";

        return compact('subject', 'bodyHtml', 'bodyText');
    }

    /** @return array<string, string> */
    private function composeIssueCommentAdded(array $payload): array
    {
        $orderNumber = (string) ($payload['order_number'] ?? 'N/A');
        $issueType = (string) ($payload['issue_type'] ?? 'ticket');
        $commenter = (string) ($payload['commenter'] ?? 'Support agent');
        $subject = ucfirst($issueType).' note added for '.($orderNumber !== 'N/A' ? "order {$orderNumber}" : 'an issue');

        $bodyHtml = implode("\n", [
            '<h1>New issue comment</h1>',
            '<p>Issue <strong>'.($payload['issue_id'] ?? 'N/A').'</strong> ('.htmlspecialchars($issueType).')</p>',
            '<p>Comment by '.htmlspecialchars($commenter).'.</p>',
            '<p>'.htmlspecialchars((string) ($payload['comment'] ?? 'A new comment is available.')).'</p>',
            '<p>For email preferences, click {{UNSUBSCRIBE_LINK}}</p>',
        ]);

        $bodyText = "New issue comment\nIssue #".($payload['issue_id'] ?? 'N/A')."\n".($payload['comment'] ?? 'A new comment is available.');

        return compact('subject', 'bodyHtml', 'bodyText');
    }

    /** @return array<string, string> */
    private function composeOrderValidationFailed(array $payload): array
    {
        $orderNumber = (string) ($payload['order_number'] ?? 'N/A');
        $importId = (string) ($payload['import_id'] ?? 'N/A');
        $errors = (string) ($payload['error_summary'] ?? 'Validation issue detected during order intake.');

        $subject = 'Order validation failed';

        $bodyHtml = implode("\n", [
            '<h1>Validation failed</h1>',
            '<p>Source order/import: '.htmlspecialchars($orderNumber).' (Import: '.htmlspecialchars($importId).')</p>',
            '<p>'.htmlspecialchars($errors).'</p>',
            '<p>Open the import queue or required actions and continue.</p>',
            '<p>For email preferences, click {{UNSUBSCRIBE_LINK}}</p>',
        ]);

        $bodyText = "Order validation failed\nSource: {$orderNumber}\nImport: {$importId}\n".strip_tags($errors);

        return compact('subject', 'bodyHtml', 'bodyText');
    }

    /** @return array<string, string> */
    private function composePaymentCompleted(array $payload): array
    {
        $orderNumber = (string) ($payload['order_number'] ?? 'N/A');
        $amount = (string) number_format(((int) ($payload['amount_cents'] ?? 0)) / 100, 2);
        $currency = (string) ($payload['currency'] ?? 'USD');

        $subject = "Payment completed for order {$orderNumber}";

        $bodyHtml = implode("\n", [
            '<h1>Order payment completed</h1>',
            '<p>Order <strong>'.htmlspecialchars($orderNumber).'</strong> has been paid.</p>',
            '<p>Amount: '.htmlspecialchars($currency).' '.htmlspecialchars($amount).'</p>',
            '<p>Order status has advanced to queued production flow.</p>',
            '<p>For email preferences, click {{UNSUBSCRIBE_LINK}}</p>',
        ]);

        $bodyText = "Order payment completed\nOrder: {$orderNumber}\nAmount: {$currency} {$amount}\nOrder status advanced to queued production flow.";

        return compact('subject', 'bodyHtml', 'bodyText');
    }

    /** @return array<string, string> */
    private function composeDefault(string $event, array $payload): array
    {
        $subject = "Portal notification: {$event}";
        $bodyHtml = '<h1>'.$subject.'</h1><pre>'.htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre><p>For email preferences, click {{UNSUBSCRIBE_LINK}}</p>';
        $bodyText = $subject."\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return compact('subject', 'bodyHtml', 'bodyText');
    }
}

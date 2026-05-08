import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function money(cents = 0, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(cents / 100);
}

export function dateLabel(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

export function statusTone(status: string) {
    if (['verified', 'shipped', 'closed', 'ready', 'resolved'].includes(status)) {
        return 'success';
    }

    if (['action_needed', 'validation_failed', 'needs_action', 'open', 'waiting_customer'].includes(status)) {
        return 'warning';
    }

    if (['cancelled', 'rejected', 'escalated'].includes(status)) {
        return 'danger';
    }

    return 'neutral';
}

export function initials(value?: string | null) {
    return (value ?? 'BC')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('') || 'BC';
}

export function humanize(value?: string | null) {
    return (value ?? '-').replace(/_/g, ' ');
}

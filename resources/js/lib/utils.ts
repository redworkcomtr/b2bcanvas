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
    if (['verified', 'shipped', 'closed', 'ready'].includes(status)) {
        return 'success';
    }

    if (['action_needed', 'validation_failed', 'needs_action', 'open'].includes(status)) {
        return 'warning';
    }

    if (['cancelled', 'rejected'].includes(status)) {
        return 'danger';
    }

    return 'neutral';
}

import { setActivePinia, createPinia } from 'pinia';
import { describe, expect, it, beforeEach } from 'vitest';

import { usePortalStore } from '@/stores/portal';

describe('portal store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('groups tickets and claims separately', () => {
        const store = usePortalStore();
        store.issues = [
            { id: 1, type: 'ticket', status: 'open', description: 'Support', order_id: null, request_type: null, reasons: [], contact: null, total_notes_count: 1, unread_notes_count: 0, last_activity_at: null, created_at: '2026-05-07T00:00:00Z' },
            { id: 2, type: 'claim', status: 'open', description: 'Credit', order_id: null, request_type: null, reasons: [], contact: null, total_notes_count: 1, unread_notes_count: 0, last_activity_at: null, created_at: '2026-05-07T00:00:00Z' },
        ];

        expect(store.openTickets).toHaveLength(1);
        expect(store.openClaims).toHaveLength(1);
    });
});

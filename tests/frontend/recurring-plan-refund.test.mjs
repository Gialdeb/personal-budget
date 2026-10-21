import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createI18n } from 'vue-i18n';

const root = new URL('../../', import.meta.url);

test('refund detail renders one independent action and linked refunded payments', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': new URL('resources/js', root).pathname } },
        server: { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    try {
        const { default: Refund } = await server.ssrLoadModule(
            '/resources/js/components/recurring/RecurringPlanRefund.vue',
        );
        const { recurringDetailMessages } = await server.ssrLoadModule(
            '/resources/js/i18n/messages/recurring-detail.ts',
        );
        const preview = {
            currency: 'EUR',
            total_cents: 0,
            account_uuid: 'resource',
            accounts: [{ uuid: 'resource', name: 'Conto' }],
            refunds: [
                {
                    uuid: 'credit-uuid',
                    date: '2026-10-21',
                    amount_cents: 10000,
                    account: 'Conto',
                    payments: [
                        {
                            uuid: 'paid-uuid',
                            date: '2026-06-16',
                            amount_cents: 10000,
                        },
                    ],
                },
            ],
        };
        const app = createSSRApp({
            render: () => h(Refund, { planUuid: 'plan', preview }),
        });
        app.use(
            createI18n({
                legacy: false,
                locale: 'it',
                messages: {
                    it: { recurringDetail: recurringDetailMessages.it },
                },
            }),
        );
        const html = await renderToString(app);
        assert.equal((html.match(/>Rimborsa</g) ?? []).length, 1);
        assert.match(html, /Rimborsi del piano/);
        assert.match(html, /Pagamento rimborsato/);
        assert.match(html, /highlight=paid-uuid/);
        assert.match(html, /highlight=credit-uuid/);
        assert.doesNotMatch(html, /Annulla piano/);
        const embedded = createSSRApp({
            render: () =>
                h(Refund, { planUuid: 'plan', preview, hideTrigger: true }),
        });
        embedded.use(
            createI18n({
                legacy: false,
                locale: 'it',
                messages: {
                    it: { recurringDetail: recurringDetailMessages.it },
                },
            }),
        );
        const embeddedHtml = await renderToString(embedded);
        assert.doesNotMatch(embeddedHtml, />Rimborsa</);
        assert.match(embeddedHtml, /highlight=paid-uuid/);
        assert.match(embeddedHtml, /highlight=credit-uuid/);
    } finally {
        await server.close();
    }
});

test('refund confirmation has one dialog, selectable date and resource, and a read-only total', async () => {
    const source = await readFile(
        new URL(
            'resources/js/components/recurring/RecurringPlanRefund.vue',
            root,
        ),
        'utf8',
    );
    assert.equal((source.match(/<Dialog\s/g) ?? []).length, 1);
    assert.match(source, /v-model="form.transaction_date"/);
    assert.match(source, /v-model="form.account_uuid"/);
    assert.match(source, /v-model="form.irreversible"/);
    assert.match(source, /data-refund-total/);
    assert.doesNotMatch(source, /v-model="[^\"]*(amount|total)/);
    assert.match(source, /crypto.randomUUID\(\)/);
    assert.match(source, /store.url\(props.planUuid\)/);
    assert.doesNotMatch(source, /confirm\(/);
    const show = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    assert.match(show, /:plan-uuid="compound\?\.uuid \?\? entry.uuid"/);
});

test('ordinary and compound headers include the refund action in their existing action groups', async () => {
    const show = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    const groups = show.slice(
        show.indexOf('data-compound-plan-actions'),
        show.indexOf('<RecurringPlanRefund'),
    );
    assert.equal(
        (groups.match(/planRefundPanel\?\.open\(\)/g) ?? []).length,
        2,
    );
    assert.equal((groups.match(/<Undo2 /g) ?? []).length, 2);
    assert.equal((groups.match(/border-emerald-200/g) ?? []).length, 2);
    assert.ok(
        groups.indexOf(
            'recurringDetail.planRefund.action',
            groups.indexOf('v-else'),
        ) < groups.indexOf('actions.can_delete'),
    );
    assert.match(show, /ref="planRefundPanel"\s+hide-trigger/);
});

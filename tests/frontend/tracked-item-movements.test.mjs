import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
let source = readFileSync(
    new URL(
        '../../resources/js/lib/tracked-item-movements.ts',
        import.meta.url,
    ),
    'utf8',
);
const optionsSource = readFileSync(
    new URL('../../resources/js/lib/tracked-item-options.ts', import.meta.url),
    'utf8',
);
const optionsCode = ts.transpileModule(optionsSource, {
    compilerOptions: { module: ts.ModuleKind.ESNext },
}).outputText;
source = source.replace(
    "'./tracked-item-options'",
    JSON.stringify(
        'data:text/javascript;base64,' +
            Buffer.from(optionsCode).toString('base64'),
    ),
);
const code = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext },
}).outputText;
const {
    movementDestinations,
    canMoveMovements,
    canRestoreMovement,
    searchMovementDestinations,
    isCurrentMovementRequest,
} = await import(
    `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`
);
test('destination choices automatically stay within the source catalog and exclude the source', () => {
    const options = [
        { value: 'source', catalog: 'personal' },
        { value: 'target', catalog: 'personal' },
        { value: 'shared', catalog: '1' },
    ];
    assert.deepEqual(
        movementDestinations(options, 'source').map((item) => item.value),
        ['target'],
    );
    assert.deepEqual(movementDestinations(options, 'missing'), []);
});
test('only a populated preview permits confirmation and never during a request', () => {
    assert.equal(canMoveMovements(null, false), false);
    assert.equal(
        canMoveMovements({ count: 0, preview_token: 'a'.repeat(64) }, false),
        false,
    );
    assert.equal(
        canMoveMovements({ count: 2, preview_token: 'a'.repeat(64) }, false),
        true,
    );
    assert.equal(
        canMoveMovements({ count: 2, preview_token: 'a'.repeat(64) }, true),
        false,
    );
});
test('the screen exposes only the movement action and name search', () => {
    const page = readFileSync(
        new URL(
            '../../resources/js/pages/settings/TrackedItems.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const dialog = readFileSync(
        new URL(
            '../../resources/js/components/tracked-items/MoveTrackedItemMovementsDialog.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const tree = readFileSync(
        new URL(
            '../../resources/js/components/tracked-items/TrackedItemsTreeList.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(tree, /Sposta movimenti/);
    assert.match(tree, /emit\('moveMovements', item\)/);
    assert.match(tree, /item.is_active && !item.canonical_uuid/);
    assert.match(page, /@move-movements="openMoveMovements"/);
    assert.match(page, /:source="movementsSource"/);
    assert.doesNotMatch(page, /@click="movementsDialogOpen = true"/);
    assert.doesNotMatch(dialog, /MobileSearchableSelect|v-model="source"/);
    assert.match(dialog, /item.full_path/);
    assert.match(dialog, /selectDestination\(item.value\)/);
    assert.doesNotMatch(
        page,
        /Consulta identità|TrackedItemMergeDialog|openIdentityDialog/,
    );
    assert.doesNotMatch(dialog, /Catalogo|slug|canonico|Unisci|<select/);
    assert.match(dialog, /Conferma spostamento/);
    assert.match(dialog, /preview.count/);
});

test('name search normalizes accents case and spacing and never offers the source or another catalog', () => {
    const options = [
        { value: 'source', label: 'Caffè', catalog: 'personal' },
        { value: 'target', label: 'Caffè   Roma', catalog: 'personal' },
        { value: 'other', label: 'Roma Caffè', catalog: 'shared' },
        {
            value: 'uuid-caffe',
            label: 'Altro',
            full_path: 'Caffè',
            catalog: 'personal',
        },
    ];
    assert.deepEqual(
        searchMovementDestinations(options, 'source', '  CAFFE   roma ').map(
            (item) => item.value,
        ),
        ['target'],
    );
    assert.deepEqual(
        searchMovementDestinations(options, 'source', 'caffe').map(
            (item) => item.value,
        ),
        ['target'],
    );
    assert.deepEqual(
        searchMovementDestinations(options, 'source', 'missing'),
        [],
    );
});
test('responses from closed dialogs or previous destination selections are ignored', () => {
    assert.equal(isCurrentMovementRequest(1, 2, true), false);
    assert.equal(isCurrentMovementRequest(2, 2, false), false);
    assert.equal(isCurrentMovementRequest(2, 2, true), true);
});

test('restore controls honor server conflicts deadline restored state and ongoing requests', () => {
    const entry = {
        can_restore: true,
        restored_at: null,
        expires_at: '2026-11-16T12:00:00Z',
    };
    const before = Date.parse('2026-11-16T11:59:59Z');
    assert.equal(canRestoreMovement(entry, false, before), true);
    assert.equal(canRestoreMovement(entry, true, before), false);
    assert.equal(
        canRestoreMovement({ ...entry, can_restore: false }, false, before),
        false,
    );
    assert.equal(
        canRestoreMovement(
            { ...entry, restored_at: '2026-11-01' },
            false,
            before,
        ),
        false,
    );
    assert.equal(
        canRestoreMovement(entry, false, Date.parse(entry.expires_at)),
        false,
    );
});
test('history shows deadlines and block reasons and requires explicit restore confirmation', () => {
    const component = readFileSync(
        new URL(
            '../../resources/js/components/tracked-items/TrackedItemMovementHistory.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const dialog = readFileSync(
        new URL(
            '../../resources/js/components/tracked-items/MoveTrackedItemMovementsDialog.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(component, /entry.block_reason/);
    assert.match(component, /entry.expires_at/);
    assert.match(component, /Conferma ripristino/);
    assert.match(component, /confirmed: true/);
    assert.match(component, /page - 1/);
    assert.match(component, /page \+ 1/);
    assert.match(dialog, /tutti i collegamenti/);
    assert.match(dialog, /preview.groups/);
    assert.match(dialog, /group.items/);
    assert.doesNotMatch(dialog, /type="checkbox"/);
});

test('protected dependency preview cannot be confirmed even with a valid signed token', () => {
    assert.equal(
        canMoveMovements(
            {
                count: 8,
                preview_token: 'a'.repeat(64),
                groups: [],
                block_reason: 'Ledger protetto',
            },
            false,
        ),
        false,
    );
    assert.equal(
        canMoveMovements(
            {
                count: 8,
                preview_token: 'a'.repeat(64),
                groups: [],
                block_reason: null,
            },
            false,
        ),
        true,
    );
});

test('movement history follows references and has an accessible shortcut above them', () => {
    const page = readFileSync(
        new URL(
            '../../resources/js/pages/settings/TrackedItems.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const history = readFileSync(
        new URL(
            '../../resources/js/components/tracked-items/TrackedItemMovementHistory.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.ok(
        page.indexOf('href="#tracked-item-movement-history"') <
            page.indexOf('<TrackedItemsTreeList'),
    );
    assert.ok(
        page.indexOf('<TrackedItemMovementHistory') >
            page.indexOf('<TrackedItemsTreeList'),
    );
    assert.equal(page.match(/<TrackedItemMovementHistory/g).length, 1);
    assert.match(page, /id="tracked-item-movement-history"/);
    assert.match(page, /@availability=/);
    assert.match(history, /data.has_restorable/);
    assert.match(history, /emit\(\s*'availability'/);
});

test('restore tabs filter on the server and notice requires an available restore with smooth navigation', () => {
    const page = readFileSync(
        new URL(
            '../../resources/js/pages/settings/TrackedItems.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const history = readFileSync(
        new URL(
            '../../resources/js/components/tracked-items/TrackedItemMovementHistory.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(page, /v-if="movementRestoresAvailable"/);
    assert.doesNotMatch(page, /movementHistoryAvailable/);
    assert.match(page, /@click.prevent="scrollToMovementHistory"/);
    assert.match(page, /prefers-reduced-motion: reduce/);
    assert.match(page, /'smooth'/);
    assert.match(history, /label: 'Da ripristinare'/);
    assert.match(history, /label: 'Ripristinati'/);
    assert.match(history, /status: status.value/);
    assert.match(history, /void load\(1\)/);
    assert.match(history, /role="tabpanel"/);
});

test('non restorable moves have a separate red tab and explain the block without a restore action', () => {
    const component = readFileSync(
        new URL(
            '../../resources/js/components/tracked-items/TrackedItemMovementHistory.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(component, /label: 'Non ripristinabili'/);
    assert.match(component, /border-red-300/);
    assert.match(component, /border-emerald-300/);
    assert.match(component, /border-sky-300/);
    assert.match(component, /v-if="entry.can_restore && !entry.restored_at"/);
    assert.match(component, /entry.block_reason && !entry.restored_at/);
    assert.match(component, /adjacentTab\(-1\)/);
});

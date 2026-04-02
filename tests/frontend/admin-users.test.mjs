import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const adminUsersTableSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/users/AdminUsersTable.vue',
        import.meta.url,
    ),
    'utf8',
);

test('admin users table exposes a dedicated mobile card layout while keeping desktop table intact', () => {
    assert.match(adminUsersTableSource, /data-test="admin-users-mobile-card"/);
    assert.match(
        adminUsersTableSource,
        /class="hidden overflow-x-auto md:block"/,
    );
    assert.match(
        adminUsersTableSource,
        /showUserBilling\(\{ user: user\.uuid }\)\.url/,
    );
    assert.match(adminUsersTableSource, /emit\('impersonate', user\)/);
    assert.match(adminUsersTableSource, /emit\('updateRoles', user\)/);
    assert.match(adminUsersTableSource, /emit\('suspend', user\)/);
    assert.match(adminUsersTableSource, /emit\('reactivate', user\)/);
    assert.match(adminUsersTableSource, /emit\('ban', user\)/);
});

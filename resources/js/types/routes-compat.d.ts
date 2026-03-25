import type * as AppearanceRoutes from '../routes/appearance/index';
import type * as BanksRoutes from '../routes/banks/index';
import type * as CategoriesRoutes from '../routes/categories/index';
import type * as SecurityRoutes from '../routes/security/index';
import type * as YearsRoutes from '../routes/years/index';

declare module '@/routes/banks' {
    export const edit: typeof BanksRoutes.edit;
    export const store: typeof BanksRoutes.store;
    export const update: typeof BanksRoutes.update;
    export const destroy: typeof BanksRoutes.destroy;
    export const toggleActive: typeof BanksRoutes.toggleActive;
}

declare module '@/routes/banks/index.ts' {
    export * from '../routes/banks/index';
}

declare module '@/routes/categories' {
    export const edit: typeof CategoriesRoutes.edit;
    export const store: typeof CategoriesRoutes.store;
    export const update: typeof CategoriesRoutes.update;
    export const destroy: typeof CategoriesRoutes.destroy;
    export const toggleActive: typeof CategoriesRoutes.toggleActive;
}

declare module '@/routes/categories/index.ts' {
    export * from '../routes/categories/index';
}

declare module '@/routes/years' {
    export const edit: typeof YearsRoutes.edit;
    export const store: typeof YearsRoutes.store;
    export const update: typeof YearsRoutes.update;
    export const destroy: typeof YearsRoutes.destroy;
    export const activate: typeof YearsRoutes.activate;
}

declare module '@/routes/years/index' {
    export * from '../routes/years/index';
}

declare module '@/routes/appearance/index' {
    export const edit: typeof AppearanceRoutes.edit;
}

declare module '@/routes/security/index' {
    export const edit: typeof SecurityRoutes.edit;
}

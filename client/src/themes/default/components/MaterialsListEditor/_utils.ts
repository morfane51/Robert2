import isValidInteger from '@/utils/isValidInteger';

import type { Event, EventMaterial } from '@/stores/api/events';

//
// - Types
//

export type MaterialQuantity = {
    id: number,
    quantity: number,
};

export type RawFilters = Record<string, number | string | string[] | boolean>;

export type MaterialsFiltersType = {
    onlySelected?: boolean,
    park?: number | null,
    category?: number | 'uncategorized' | null,
    subCategory?: number | null,
    tags?: string[],
};

//
// - Filtres
//

export const normalizeFilters = (rawFilters: RawFilters, extended: boolean = true): MaterialsFiltersType => {
    const filters: MaterialsFiltersType = extended
        ? { park: null, category: null, subCategory: null, tags: [] }
        : {};

    if ('onlySelected' in rawFilters) {
        filters.onlySelected = !!rawFilters.onlySelected;
    }

    if ('tags' in rawFilters && Array.isArray(rawFilters.tags)) {
        filters.tags = rawFilters.tags!;
    }

    if ('category' in rawFilters) {
        if (rawFilters.category === 'uncategorized') {
            filters.category = 'uncategorized';
        } else if (isValidInteger(rawFilters.category)) {
            filters.category = parseInt(rawFilters.category as string, 10);
        }
    }

    ['park', 'subCategory'].forEach((key: string) => {
        if (key in rawFilters && isValidInteger(rawFilters[key])) {
            // @ts-expect-error - Ici, on sait que `key` est un nombre.
            filters[key] = parseInt(rawFilters[key] as string, 10);
        }
    });

    return filters;
};

//
// - Quantités
//

export const getEventMaterialsQuantities = (materials: Event['materials']): MaterialQuantity[] => (
    materials.map(({ id, pivot }: EventMaterial) => {
        const data = { id, quantity: pivot?.quantity || 0 };
        return data;
    })
);

const materialComparatorBuilder = (a: MaterialQuantity) => (b: MaterialQuantity) => {
    if (a.id !== b.id) {
        return false;
    }

    return a.quantity === b.quantity;
};

export const materialsHasChanged = (before: MaterialQuantity[], after: MaterialQuantity[]): boolean => {
    // - Si un nouveau matériel n'est pas identique à un matériel déjà sauvé.
    const differencesNew = after.filter((newMaterial: MaterialQuantity) => {
        if (newMaterial.quantity === 0) {
            return false;
        }
        return !before.some(materialComparatorBuilder(newMaterial));
    });
    if (differencesNew.length > 0) {
        return true;
    }

    // - Si un matériel sauvé n'existe plus ou a changé dans le nouveau jeu de données.
    const differencesOld = before.filter((oldMaterial: MaterialQuantity) => {
        if (oldMaterial.quantity === 0) {
            return false;
        }
        return !after.some(materialComparatorBuilder(oldMaterial));
    });

    return differencesOld.length > 0;
};

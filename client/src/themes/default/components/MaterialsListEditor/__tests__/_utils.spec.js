import {
    getEventMaterialsQuantities,
    materialsHasChanged,
} from '../_utils';

describe('MaterialsListEditor', () => {
    describe('Utils / getEventMaterialsQuantities()', () => {
        it('should return an object containing the quantities related to an event material', () => {
            const materials = [
                { id: 1, pivot: { quantity: 2 } },
                { id: 2, pivot: { quantity: 6 } },
                { id: 3, pivot: { quantity: 0 } },
                { id: 4 },
                { id: 5 },
                { id: 6 },
            ];
            expect(getEventMaterialsQuantities(materials)).toEqual([
                { id: 1, quantity: 2 },
                { id: 2, quantity: 6 },
                { id: 3, quantity: 0 },
                { id: 4, quantity: 0 },
                { id: 5, quantity: 0 },
                { id: 6, quantity: 0 },
            ]);
        });
    });

    describe('Utils / materialsHasChanged()', () => {
        it('should return true if the quantity of a previously saved material has changed', () => {
            // - Test simple de changement de quantité.
            const before1 = [{ id: 1, quantity: 1 }];
            const after1 = [{ id: 1, quantity: 2 }];
            expect(materialsHasChanged(before1, after1)).toBe(true);

            // - Test de suppression
            const before5 = [{ id: 1, quantity: 1 }];
            expect(materialsHasChanged(before5, [])).toBe(true);

            // - Test avec plusieurs matériels.
            const before6 = [
                { id: 1, quantity: 1 },
                { id: 2, quantity: 1 },
            ];
            const after6 = [
                { id: 1, quantity: 1 },
                { id: 2, quantity: 3 },
            ];
            expect(materialsHasChanged(before6, after6)).toBe(true);
        });

        it('should return true if a new material has been added', () => {
            const after = [{ id: 1, quantity: 1 }];
            expect(materialsHasChanged([], after)).toBe(true);
        });

        it('should return false if a material has been added with a quantity of 0', () => {
            const after = [{ id: 1, quantity: 0 }];
            expect(materialsHasChanged([], after)).toBe(false);
        });

        it('should return false if a previously saved material has not changed', () => {
            // - Test simple de changement de quantité.
            const before1 = [{ id: 1, quantity: 1 }];
            const after1 = [{ id: 1, quantity: 1 }];
            expect(materialsHasChanged(before1, after1)).toBe(false);

            // - Test avec plusieurs matériels.
            const before4 = [
                { id: 1, quantity: 1 },
                { id: 2, quantity: 1 },
            ];
            const after4 = [
                { id: 1, quantity: 1 },
                { id: 2, quantity: 3 },
            ];
            expect(materialsHasChanged(before4, after4)).toBe(true);
        });
    });
});

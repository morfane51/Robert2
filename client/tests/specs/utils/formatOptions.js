import formatOptions from '@/utils/formatOptions';

describe('formatOptions', () => {
    it('returns an empty array when there is no data and/or no placeholder', () => {
        expect(formatOptions()).toEqual([]);
        expect(formatOptions([])).toEqual([]);
        expect(formatOptions(null)).toEqual([]);
    });

    it('returns a set of options with given list of entities', () => {
        const entities = [
            { id: 1, name: 'test1' },
            { id: 2, name: 'test2' },
        ];
        const options = formatOptions(entities);
        expect(options).toEqual([
            { value: 1, label: 'test1' },
            { value: 2, label: 'test2' },
        ]);
    });

    it('returns a set of options with given list of entities and custom function to create label', () => {
        const entities = [
            { id: 1, title: 'test1', phone: '0123456789' },
            {
                id: 2,
                title: 'test2',
                phone: '0987654321',
                company: { id: 1, name: 'Testing' },
            },
        ];
        const getLabel = ({ title, phone, company }) => {
            const companyName = company?.name || '';
            return `${title} ${phone} − ${companyName}`;
        };

        const options = formatOptions(entities, getLabel);
        expect(options).toEqual([
            { value: 1, label: 'test1 0123456789 − ' },
            { value: 2, label: 'test2 0987654321 − Testing' },
        ]);
    });
});

import './index.scss';
import VueSelect from 'vue-select';
import debounce from 'lodash/debounce';
import { DEBOUNCE_WAIT } from '@/globals/constants';
import formatOptions from '@/utils/formatOptions';
import Button from '@/themes/default/components/Button';
import ErrorMessage from '@/themes/default/components/ErrorMessage';
import Icon from '@/themes/default/components/Icon';
import apiCompanies from '@/stores/api/companies';

// @vue/component
export default {
    name: 'CompanySelect',
    props: {
        defaultCompany: { type: Object, default: null },
    },
    data() {
        const hasDefaultValue = !!this.defaultCompany;
        const defaultItem = {
            value: this.defaultCompany?.id || null,
            label: this.defaultCompany?.legal_name || null,
        };

        return {
            selectedValue: hasDefaultValue ? defaultItem : null,
            companies: hasDefaultValue ? [defaultItem] : [],
            searchError: null,
        };
    },
    created() {
        this.debouncedSearch = debounce(this.search.bind(this), DEBOUNCE_WAIT);
    },
    beforeDestroy() {
        this.debouncedSearch.cancel();
    },
    methods: {
        async search(loading, search) {
            try {
                this.searchError = null;
                const data = await apiCompanies.all({ search, limit: 10 });
                this.companies = formatOptions(data.data, (item) => item.legal_name);
            } catch (error) {
                this.searchError = error;
            } finally {
                loading(false);
            }
        },

        handleSearch(searchTerm, loading) {
            if (searchTerm.length < 2) {
                return;
            }
            loading(true);
            this.debouncedSearch(loading, searchTerm);
        },

        handleChange(selection) {
            this.selectedValue = selection;
            const { value } = selection || { value: null };
            this.$emit('change', value);
        },
    },
    render() {
        const {
            $t: __,
            selectedValue,
            companies,
            searchError,
            handleSearch,
            handleChange,
        } = this;

        return (
            <div class="CompanySelect">
                <VueSelect
                    options={companies}
                    value={selectedValue}
                    filterable={false}
                    multiple={false}
                    onSearch={handleSearch}
                    onInput={handleChange}
                    placeholder={__('page.beneficiary.type-to-search-company')}
                    class="CompanySelect__select"
                >
                    <div slot="no-options">
                        <p>{__('no-result-found-try-another-search')}</p>
                        <p>
                            <router-link to={{ name: 'add-company' }}>
                                <Icon name="plus" /> {__('create-company')}
                            </router-link>
                        </p>
                    </div>
                </VueSelect>
                {selectedValue?.value && (
                    <Button
                        class="CompanySelect__edit-button"
                        icon="edit"
                        type="default"
                        to={{
                            name: 'edit-company',
                            params: { id: selectedValue.value },
                        }}
                    >
                        {__('action-edit')}
                    </Button>
                )}
                {searchError && <ErrorMessage error={searchError} />}
            </div>
        );
    },
};

import './index.scss';
import axios from 'axios';
import diff from 'lodash/difference';
import { defineComponent } from '@vue/composition-api';
import HttpCode from 'status-code-enum';
import { isRequestErrorStatusCode } from '@/utils/errors';
import { confirm } from '@/utils/alert';
import { ApiErrorCode } from '@/stores/api/@codes';
import apiAttributes from '@/stores/api/attributes';
import Page from '@/themes/default/components/Page';
import CriticalError from '@/themes/default/components/CriticalError';
import Loading from '@/themes/default/components/Loading';
import Form from './components/Form';

import type { AttributeDetails, AttributeEdit } from '@/stores/api/attributes';
import type { Category } from '@/stores/api/categories';

type Data = {
    id: AttributeDetails['id'] | null,
    isFetched: boolean,
    isSaving: boolean,
    attribute: AttributeDetails | null,
    criticalError: boolean,
    validationErrors: Record<string, string[]> | undefined,
};

// @vue/component
const AttributeEditPage = defineComponent({
    name: 'AttributeEditPage',
    data(): Data {
        const id = this.$route.params.id
            ? parseInt(this.$route.params.id, 10)
            : null;

        return {
            id,
            isFetched: false,
            isSaving: false,
            attribute: null,
            criticalError: false,
            validationErrors: undefined,
        };
    },
    computed: {
        isNew() {
            return this.id === null;
        },

        pageTitle() {
            const { $t: __, isNew, isFetched, attribute } = this;

            if (isNew) {
                return __('page.attribute-edit.title-create');
            }

            if (!isFetched) {
                return __('page.attribute-edit.title-simple');
            }

            return __('page.attribute-edit.title', { name: attribute!.name });
        },
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        // ------------------------------------------------------
        // -
        // -    Handlers
        // -
        // ------------------------------------------------------

        handleSubmit(data: AttributeEdit) {
            this.save(data);
        },

        handleCancel() {
            this.$router.back();
        },

        // ------------------------------------------------------
        // -
        // -    Méthodes internes
        // -
        // ------------------------------------------------------

        async fetchData() {
            const { isNew, id } = this;
            if (isNew) {
                this.isFetched = true;
                return;
            }

            try {
                this.attribute = await apiAttributes.one(id!);
            } catch {
                this.criticalError = true;
            } finally {
                this.isFetched = true;
            }
        },

        async save(data: AttributeEdit) {
            if (this.isSaving) {
                return;
            }

            const { $t: __, attribute, isNew } = this;

            const savedCategories = attribute?.categories?.map(({ id }: Category) => id) ?? [];
            const hasCategories = data.categories.length > 0;
            const hasRemovedCategories = diff(savedCategories, data.categories).length > 0;
            const hasAddedCategories = diff(data.categories, savedCategories).length > 0;

            if (!isNew && hasCategories && (hasRemovedCategories || hasAddedCategories)) {
                const isConfirmed = await confirm({
                    title: __('please-confirm'),
                    text: __('page.attribute-edit.confirm-update-categories'),
                    confirmButtonText: __('page.attribute-edit.yes-update'),
                    type: 'warning',
                });

                if (!isConfirmed) {
                    return;
                }
            }

            this.isSaving = true;

            try {
                if (isNew) {
                    await apiAttributes.create(data);
                } else {
                    this.attribute = await apiAttributes.update(this.id!, data);
                }

                this.validationErrors = undefined;

                // - Redirection...
                this.$toasted.success(__('page.attribute-edit.saved'));
                this.$router.replace({ name: 'attributes' });
            } catch (error) {
                if (axios.isAxiosError(error) && isRequestErrorStatusCode(error, HttpCode.ClientErrorBadRequest)) {
                    const defaultError = { code: ApiErrorCode.UNKNOWN, details: {} };
                    const { code, details } = error.response?.data?.error ?? defaultError;
                    if (code === ApiErrorCode.VALIDATION_FAILED) {
                        this.validationErrors = { ...details };
                        this.$refs.page.scrollToTop();
                        return;
                    }
                }

                this.$toasted.error(__('errors.unexpected-while-saving'));
            } finally {
                this.isSaving = false;
            }
        },
    },
    render() {
        const {
            criticalError,
            isFetched,
            pageTitle,
            validationErrors,
            attribute,
            isSaving,
            handleSubmit,
            handleCancel,
        } = this;

        if (criticalError || !isFetched) {
            return (
                <Page name="attribute-edit" title={pageTitle}>
                    {criticalError ? <CriticalError type={criticalError} /> : <Loading />}
                </Page>
            );
        }

        return (
            <Page
                ref="page"
                name="attribute-edit"
                title={pageTitle}
                hasValidationError={!!validationErrors}
            >
                <div class="AttributeEditPage">
                    <Form
                        savedData={attribute}
                        isSaving={isSaving}
                        errors={validationErrors}
                        onSubmit={handleSubmit}
                        onCancel={handleCancel}
                    />
                </div>
            </Page>
        );
    },
});

export default AttributeEditPage;

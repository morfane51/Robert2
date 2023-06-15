import './index.scss';
import { defineComponent } from '@vue/composition-api';
import apiTechnicians from '@/stores/api/technicians';
import apiDocuments from '@/stores/api/documents';
import CriticalError from '@/themes/default/components/CriticalError';
import FileManager from '@/themes/default/components/FileManager';
import Loading from '@/themes/default/components/Loading';
import { confirm } from '@/utils/alert';

import type { ProgressCallback } from 'axios';
import type { PropType } from '@vue/composition-api';
import type { Document } from '@/stores/api/documents';
import type { Technician } from '@/stores/api/technicians';

type Props = {
    /** Le technicien dont on veut gérer les documents. */
    technician: Technician,
};

type State = {
    isFetched: boolean,
    hasCriticalError: boolean,
    documents: Document[],
};

// @vue/component
const TechnicianViewDocuments = defineComponent({
    name: 'TechnicianViewDocuments',
    props: {
        technician: {
            type: Object as PropType<Required<Props>['technician']>,
            required: true,
        },
    },
    data: (): State => ({
        hasCriticalError: false,
        isFetched: false,
        documents: [],
    }),
    mounted() {
        this.fetchDocuments();

        this.persistDocument.bind(this);
    },
    methods: {
        // ------------------------------------------------------
        // -
        // -    Handlers
        // -
        // ------------------------------------------------------

        handleDocumentUploaded(document: Document) {
            this.documents.push(document);
        },

        async handleDocumentDelete(id: Document['id']) {
            const index = this.documents.findIndex((document: Document) => document.id === id);
            if (index === -1) {
                return;
            }

            const { $t: __ } = this;
            const isConfirmed = await confirm({
                type: 'danger',
                text: __('page.technician-view.documents.confirm-permanently-delete'),
                confirmButtonText: __('yes-permanently-delete'),
            });
            if (!isConfirmed) {
                return;
            }

            this.$delete(this.documents, index);
            try {
                await apiDocuments.remove(id);
                this.$toasted.success(__('page.technician-view.documents.deleted'));
            } catch {
                this.$toasted.error(__('errors.unexpected-while-deleting'));
                this.fetchDocuments();
            }
        },

        // ------------------------------------------------------
        // -
        // -    API Publique
        // -
        // ------------------------------------------------------

        isUploading(): boolean {
            const { fileManagerRef } = this.$refs;
            return !!fileManagerRef?.isUploading();
        },

        // ------------------------------------------------------
        // -
        // -    Méthodes internes
        // -
        // ------------------------------------------------------

        async fetchDocuments(): Promise<void> {
            try {
                const data = await apiTechnicians.documents(this.technician.id);
                this.documents = data;
                this.isFetched = true;
            } catch {
                this.hasCriticalError = true;
            }
        },

        persistDocument(file: File, signal: AbortSignal, onProgress: ProgressCallback): Promise<Document> {
            return apiTechnicians.attachDocument(this.technician.id, file, { onProgress, signal });
        },
    },
    render() {
        const {
            isFetched,
            hasCriticalError,
            persistDocument,
            documents,
            handleDocumentUploaded,
            handleDocumentDelete,
        } = this;

        if (hasCriticalError || !isFetched) {
            return (
                <div class="TechnicianViewDocuments">
                    {hasCriticalError ? <CriticalError /> : <Loading />}
                </div>
            );
        }

        return (
            <div class="TechnicianViewDocuments">
                <FileManager
                    ref="fileManagerRef"
                    class="TechnicianViewDocuments__manager"
                    documents={documents}
                    persister={persistDocument}
                    onDocumentUploaded={handleDocumentUploaded}
                    onDocumentDelete={handleDocumentDelete}
                />
            </div>
        );
    },
});

export default TechnicianViewDocuments;

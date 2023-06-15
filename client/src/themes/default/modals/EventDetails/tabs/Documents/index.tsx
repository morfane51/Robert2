import { defineComponent } from '@vue/composition-api';
import apiEvents from '@/stores/api/events';
import apiDocuments from '@/stores/api/documents';
import CriticalError from '@/themes/default/components/CriticalError';
import FileManager, { FileManagerLayout } from '@/themes/default/components/FileManager';
import Loading from '@/themes/default/components/Loading';
import { confirm } from '@/utils/alert';

import type { ProgressCallback } from 'axios';
import type { PropType } from '@vue/composition-api';
import type { Document } from '@/stores/api/documents';
import type { Event } from '@/stores/api/events';

type Props = {
    /** L'événement dont on veut gérer les documents. */
    event: Event,
};

type State = {
    isFetched: boolean,
    hasCriticalError: boolean,
    documents: Document[],
};

// @vue/component
const EventDetailsDocuments = defineComponent({
    name: 'EventDetailsDocuments',
    props: {
        event: {
            type: Object as PropType<Required<Props>['event']>,
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
                text: __('modal.event-details.documents.confirm-permanently-delete'),
                confirmButtonText: __('yes-permanently-delete'),
            });
            if (!isConfirmed) {
                return;
            }

            this.$delete(this.documents, index);
            try {
                await apiDocuments.remove(id);
                this.$toasted.success(__('modal.event-details.documents.deleted'));
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
                const data = await apiEvents.documents(this.event.id);
                this.documents = data;
                this.isFetched = true;
            } catch {
                this.hasCriticalError = true;
            }
        },

        persistDocument(file: File, signal: AbortSignal, onProgress: ProgressCallback): Promise<Document> {
            return apiEvents.attachDocument(this.event.id, file, { onProgress, signal });
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
                <div class="EventDetailsDocuments">
                    {hasCriticalError ? <CriticalError /> : <Loading />}
                </div>
            );
        }

        return (
            <div class="EventDetailsDocuments">
                <FileManager
                    ref="fileManagerRef"
                    class="EventDetailsDocuments__manager"
                    layout={FileManagerLayout.VERTICAL}
                    documents={documents}
                    persister={persistDocument}
                    onDocumentUploaded={handleDocumentUploaded}
                    onDocumentDelete={handleDocumentDelete}
                />
            </div>
        );
    },
});

export default EventDetailsDocuments;

import './index.scss';
import { defineComponent } from '@vue/composition-api';
import apiEvents from '@/stores/api/events';
import Fragment from '@/components/Fragment';
import Icon from '@/themes/default/components/Icon';

// @vue/component
const EventMissingMaterials = {
    name: 'EventMissingMaterials',
    props: {
        eventId: { type: Number, required: true },
    },
    data() {
        return {
            hasFetchError: false,
            missingMaterials: [],
        };
    },
    computed: {
        hasMissingMaterials() {
            return this.missingMaterials.length > 0;
        },
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        async fetchData() {
            const { eventId } = this;
            try {
                this.missingMaterials = await apiEvents.missingMaterials(eventId);
            } catch {
                this.hasFetchError = true;
            }
        },
    },
    render() {
        const { $t: __, missingMaterials, hasFetchError, hasMissingMaterials } = this;

        if (!hasMissingMaterials && !hasFetchError) {
            return null;
        }

        return (
            <div class="EventMissingMaterials">
                <h3 class="EventMissingMaterials__title">
                    <Icon name="box-open" />&nbsp;
                    {__('@event.event-missing-materials')}
                </h3>
                {hasFetchError && (
                    <div class="EventMissingMaterials__error">
                        <Icon name="exclamation-circle" />&nbsp;
                        {__('errors.unexpected-while-fetching')}
                    </div>
                )}
                {!hasFetchError && (
                    <Fragment>
                        <p class="EventMissingMaterials__help">
                            {__('@event.event-missing-materials-help')}
                        </p>
                        <ul class="EventMissingMaterials__list">
                            {missingMaterials.map((missingMaterial) => (
                                <li
                                    key={missingMaterial.id}
                                    class="EventMissingMaterials__item"
                                >
                                    <div class="EventMissingMaterials__item__name">
                                        {missingMaterial.name}
                                    </div>
                                    <div class="EventMissingMaterials__item__quantity">
                                        {__('@event.missing-material-count', {
                                            quantity: missingMaterial.pivot.quantity,
                                            missing: missingMaterial.pivot.quantity_missing,
                                        })}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Fragment>
                )}
            </div>
        );
    },
};

export default defineComponent(EventMissingMaterials);

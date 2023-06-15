import './index.scss';
import moment from 'moment';
import dateRoundMinutes from '@/utils/dateRoundMinutes';
import { confirm } from '@/utils/alert';
import showModal from '@/utils/showModal';
import CriticalError from '@/themes/default/components/CriticalError';
import Help from '@/themes/default/components/Help';
import Loading from '@/themes/default/components/Loading';
import Timeline from '@/themes/default/components/Timeline';
import apiEvents from '@/stores/api/events';
import apiTechnicians from '@/stores/api/technicians';
import formatEventTechnician from '@/utils/formatEventTechnician';
import EventStore from '../../EventStore';
import Modal from './Modal';
import { ApiErrorCode } from '@/stores/api/@codes';
import {
    TECHNICIAN_EVENT_STEP,
    TECHNICIAN_EVENT_MIN_DURATION,
    DATE_DB_FORMAT,
} from '@/globals/constants';

const getClosestStepTime = (requestedTime, roundMethod = 'floor') => {
    const time = moment(requestedTime).startOf('minute');
    return moment(Math[roundMethod](+time / +TECHNICIAN_EVENT_STEP) * +TECHNICIAN_EVENT_STEP);
};

// @vue/component
export default {
    name: 'EventStep3',
    props: {
        event: { type: Object, required: true },
    },
    data() {
        return {
            technicians: null,
            isLoading: false,
            isModalOpened: false,
            hasCriticalError: false,
        };
    },
    computed: {
        techniciansCount() {
            return this.technicians?.length || 0;
        },

        groups() {
            if (this.techniciansCount === 0) {
                return null;
            }

            return this.technicians.map((technician) => {
                const { first_name: firstName, last_name: lastName } = technician;
                return { id: technician.id, content: `${lastName} ${firstName}` };
            });
        },

        events() {
            if (this.technicians === null) {
                return null;
            }

            const { event } = this;

            const eventSlots = (event?.technicians ?? []).map((eventTechnician) => {
                const { technician_id: technicianId } = eventTechnician;
                const { id, start, end, content, title } = formatEventTechnician({
                    ...eventTechnician,
                    event,
                });

                return {
                    id,
                    start,
                    end,
                    content,
                    group: technicianId,
                    editable: true,
                    type: 'range',
                    title,
                };
            });

            const otherSlots = this.technicians.map((technician) => (
                (technician?.events ?? []).map((eventTechnician) => {
                    const { id, start, end, title } = formatEventTechnician(eventTechnician);

                    return {
                        id,
                        start,
                        end,
                        content: title,
                        group: technician.id,
                        editable: false,
                        type: 'background',
                        // - Pas de `title`, car la tooltip ne fonctionne pas pour le type 'background'...
                    };
                })
            ));

            return [...eventSlots, ...otherSlots.flat()];
        },

        timelineOptions() {
            const { start_date: startDate, end_date: endDate } = this.event;

            return {
                min: startDate,
                max: endDate,
                showCurrentTime: false,
                margin: { axis: 0 },
                type: 'background',
                zoomMin: 1000 * 3600, // 1h
                selectable: true,
            };
        },
    },
    mounted() {
        EventStore.commit('setIsSaved', true);

        this.fetchAllTechnicians();
    },
    methods: {
        // ------------------------------------------------------
        // -
        // -    Handlers
        // -
        // ------------------------------------------------------

        handleDoubleClick(e) {
            // - On évite le double-call à cause d'un bug qui trigger l'event en double.
            // - @see Vis-js bug here: https://github.com/visjs/vis-timeline/issues/301)
            if (this.isModalOpened) {
                return;
            }

            //
            // - Édition d'une assignation
            //
            const eventTechnician = (e.item && (this.event?.technicians ?? [])
                .find(({ id }) => id === e.item)) ?? undefined;
            if (eventTechnician !== undefined) {
                this.openModal(eventTechnician.id);
                return;
            }

            //
            // - Création d'une assignation
            //

            if (!e.group || !e.time) {
                return;
            }

            const technician = this.technicians.find(({ id }) => id === e.group);
            if (technician === undefined) {
                return;
            }

            const requestedTime = getClosestStepTime(e.time);
            let time = requestedTime.clone();
            const isRequestedTimeAvailable = !(technician?.events ?? []).some(
                ({ start_time: start, end_time: end }) => {
                    if (requestedTime.isBetween(start, end, undefined, '[)')) {
                        return true;
                    }

                    // - Si l'heure demandée est trop proche de l'événement suivant pour pouvoir y caser
                    //   la durée min. d'un événement, on décale l'heure pour que ça rentre.
                    const startTimeOffset = moment(start).subtract(TECHNICIAN_EVENT_MIN_DURATION);
                    if (requestedTime.isBetween(startTimeOffset, start, undefined, '[]') && time.isAfter(startTimeOffset)) {
                        time = startTimeOffset;
                    }

                    return false;
                },
            );
            if (!isRequestedTimeAvailable) {
                return;
            }

            // - On vérifie que l'heure ajustée est bien disponible.
            if (!requestedTime.isSame(time)) {
                const isAdjustedTimeAvailable = !(technician?.events ?? []).some(
                    ({ start_time: start, end_time: end }) => {
                        const startTimeOffset = moment(start).subtract(TECHNICIAN_EVENT_MIN_DURATION);
                        return time.isBetween(startTimeOffset, end, undefined, '()');
                    },
                );
                if (!isAdjustedTimeAvailable) {
                    return;
                }
            }

            this.openModal({
                technician,
                eventId: this.event.id,
                startTime: time.format('YYYY-MM-DD HH:mm'),
            });
        },

        async handleItemMoved(item, callback) {
            const data = {
                start_time: moment(dateRoundMinutes(item.start)).utc().format(DATE_DB_FORMAT),
                end_time: moment(dateRoundMinutes(item.end)).utc().format(DATE_DB_FORMAT),
            };

            try {
                this.hasCriticalError = false;
                this.isLoading = true;
                await this.$http.put(`event-technicians/${item.id}`, data);
                this.handleItemUpdated();
                callback(item);
            } catch (error) {
                callback(null);
                const { code, details } = error.response?.data?.error || { code: ApiErrorCode.UNKNOWN, details: null };
                if (code === ApiErrorCode.VALIDATION_FAILED && details.start_time && details.start_time.length > 0) {
                    this.$toasted.error(details.start_time[0]);
                }
            } finally {
                this.isLoading = false;
            }
        },

        async handleItemRemove(item, callback) {
            const { $t: __ } = this;

            const isConfirmed = await confirm({
                text: __('page.event-edit.technician-item.confirm-permanently-delete'),
                confirmButtonText: __('yes-permanently-delete'),
                type: 'danger',
            });
            if (!isConfirmed) {
                callback(null);
                return;
            }

            try {
                this.isLoading = true;
                await this.$http.delete(`event-technicians/${item.id}`);
                callback(item);
            } catch {
                callback(null);
            } finally {
                this.isLoading = false;
            }
        },

        handleItemUpdated() {
            this.isLoading = false;
            this.hasCriticalError = false;
            this.updateEvent();
        },

        handlePrevClick() {
            this.$emit('gotoStep', 2);
        },

        handleNextClick() {
            this.$emit('gotoStep', 4);
        },

        // ------------------------------------------------------
        // -
        // -    Méthodes internes
        // -
        // ------------------------------------------------------

        async fetchAllTechnicians() {
            this.isLoading = true;

            try {
                this.technicians = await apiTechnicians.allWhileEvent(this.event.id);
            } catch {
                this.hasCriticalError = true;
            } finally {
                this.isLoading = false;
            }
        },

        async updateEvent() {
            try {
                const data = await apiEvents.one(this.event.id);
                this.$emit('updateEvent', data, { save: true });
            } catch {
                this.hasCriticalError = true;
            } finally {
                this.isLoading = false;
            }
        },

        openModal(data) {
            const { start_date: start, end_date: end } = this.event;

            this.isModalOpened = true;
            showModal(this.$modal, Modal, {
                data,
                eventDates: { start, end },
                onClose: () => {
                    this.isLoading = true;
                    this.updateEvent();
                },
                onClosed: () => {
                    this.isModalOpened = false;
                },
            });
        },
    },
    render() {
        const {
            $t: __,
            isLoading,
            hasCriticalError,
            events,
            techniciansCount,
            groups,
            timelineOptions,
            handleNextClick,
            handleDoubleClick,
            handlePrevClick,
            handleItemMoved,
            handleItemRemove,
            handleItemUpdated,
        } = this;

        const renderContent = () => {
            if (hasCriticalError) {
                return <CriticalError />;
            }

            if (techniciansCount === 0 && !isLoading) {
                return (
                    <div class="EventStep3__no-technician">
                        <p class="EventStep3__no-technician__message">
                            {__('page.event-edit.no-technician-pass-this-step')}
                        </p>
                        <button type="button" class="button success" onClick={handleNextClick}>
                            {__('page.event-edit.continue')} <i class="fas fa-arrow-right" />
                        </button>
                    </div>
                );
            }

            return (
                <Timeline
                    class="EventStep3__timeline"
                    items={events}
                    groups={groups}
                    options={timelineOptions}
                    onDoubleClick={handleDoubleClick}
                    onItemMoved={handleItemMoved}
                    onItemRemove={handleItemRemove}
                    onItemRemoved={handleItemUpdated}
                    minutesGrid={15}
                />
            );
        };

        return (
            <div class="EventStep3">
                <header class="EventStep3__header">
                    <h1 class="EventStep3__title">{__('page.event-edit.event-technicians')}</h1>
                    {isLoading && <Loading horizontal />}
                    {!isLoading && techniciansCount > 0 && (
                        <Help message={__('page.event-edit.technicians-help')} />
                    )}
                </header>
                <div class="EventStep3__content">
                    {renderContent()}
                </div>
                <section class="EventStep3__footer">
                    <button type="button" class="button info" onClick={handlePrevClick}>
                        <i class="fas fa-arrow-left" />&nbsp;
                        {__('page.event-edit.save-and-go-to-prev-step')}
                    </button>
                    {!isLoading && techniciansCount > 0 && (
                        <button type="button" class="button success" onClick={handleNextClick}>
                            {__('page.event-edit.save-and-go-to-next-step')}&nbsp;
                            <i class="fas fa-arrow-right" />
                        </button>
                    )}
                </section>
            </div>
        );
    },
};

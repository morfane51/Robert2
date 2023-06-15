import './index.scss';
import { defineComponent } from '@vue/composition-api';
import ErrorMessage from '@/themes/default/components/ErrorMessage';

// @vue/component
export default defineComponent({
    name: 'Help',
    props: {
        message: { type: [String, Object], required: true },
        error: { type: [String, Error], default: null },
        isLoading: { type: Boolean, default: false },
    },
    computed: {
        modifier() {
            if (this.isLoading) {
                return 'info';
            }

            if (this.error) {
                return 'error';
            }

            return this.message.type || 'info';
        },

        messageText() {
            const { $t: __, message } = this;
            return __(message.text || message);
        },
    },
    render() {
        const { $t: __, isLoading, error, modifier, messageText } = this;

        return (
            <div class={['Help', `Help--${modifier}`]}>
                {isLoading && (
                    <div class="Help__loading">
                        <i class="fas fa-circle-notch fa-spin" /> {__('loading')}
                    </div>
                )}
                {!!(!isLoading && error) && <ErrorMessage error={error} />}
                {!!(messageText && !error && !isLoading) && (
                    <div class="Help__message">{messageText}</div>
                )}
            </div>
        );
    },
});

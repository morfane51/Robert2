import './index.scss';
import { toRefs, computed } from '@vue/composition-api';
import Fragment from '@/components/Fragment';
import Icon from '@/themes/default/components/Icon';
import useI18n from '@/hooks/useI18n';

const TYPES = ['default', 'success', 'warning', 'danger', 'primary', 'secondary', 'transparent'];

// NOTE: L'idée ici n'est pas d'ajouter tous les types possibles mais uniquement ceux
// qui se retrouvent à de multiples endroits (pour éviter d'avoir des soucis de cohérence)
const PREDEFINED_TYPES = {
    add: {
        type: 'success',
        icon: 'plus',
    },
    edit: (__) => ({
        type: 'success',
        icon: 'edit',
        tooltip: __('action-edit'),
    }),
    trash: (__) => ({
        type: 'danger',
        icon: 'trash',
        tooltip: __('action-trash'),
    }),
    delete: (__) => ({
        type: 'danger',
        icon: 'trash-alt',
        tooltip: __('action-delete'),
    }),
    restore: (__) => ({
        type: 'success',
        icon: 'trash-restore',
        tooltip: __('action-restore'),
    }),
    close: {
        type: 'transparent',
        icon: 'times',
    },
};

// @vue/component
const Button = (props, { slots, emit }) => {
    const __ = useI18n();
    const {
        htmlType,
        icon,
        disabled,
        type,
        size,
        to,
        external,
        loading,
        tooltip,
    } = toRefs(props);

    const handleClick = (e) => {
        if (disabled.value) {
            return;
        }
        emit('click', e);
    };

    const getPredefinedValue = (key) => {
        if (!Object.keys(PREDEFINED_TYPES).includes(type.value)) {
            return undefined;
        }

        const value = typeof PREDEFINED_TYPES[type.value] === 'function'
            ? PREDEFINED_TYPES[type.value](__)
            : PREDEFINED_TYPES[type.value];

        return value[key] ?? null;
    };

    const _type = computed(() => {
        const predefinedValue = getPredefinedValue('type');
        return predefinedValue !== undefined
            ? (predefinedValue ?? 'default')
            : type.value;
    });

    const _icon = computed(() => {
        const __icon = icon.value ?? getPredefinedValue('icon');
        if (!__icon) {
            return null;
        }

        if (loading.value) {
            return { name: 'spinner', spin: true };
        }

        if (!__icon.includes(':')) {
            return { name: __icon };
        }

        const [iconType, variant] = __icon.split(':');
        return { name: iconType, variant };
    });

    const _tooltip = computed(() => {
        const predefinedValue = getPredefinedValue('tooltip');
        if ([undefined, null].includes(predefinedValue) || typeof tooltip.value === 'string') {
            return tooltip.value;
        }

        if ([undefined, null].includes(tooltip.value)) {
            return predefinedValue;
        }

        return {
            ...tooltip.value,
            'content': tooltip.value.content ?? predefinedValue,
        };
    });

    const _className = computed(() => [
        'Button',
        `Button--${_type.value}`,
        `Button--${size.value}`,
        {
            'Button--disabled': disabled.value || loading.value,
            'Button--loading': loading.value,
            'Button--with-icon': !!_icon.value,
        },
    ]);

    return () => {
        const children = slots.default?.();

        const content = (
            <Fragment>
                {_icon.value && <Icon {...{ props: _icon.value }} class="Button__icon" />}
                {children && <span class="Button__content">{children}</span>}
            </Fragment>
        );

        if (to.value && !disabled.value) {
            if (external.value) {
                const isOutside = typeof to.value === 'string' && to.value.includes('://');

                return (
                    <a
                        href={to.value}
                        v-tooltip={_tooltip.value}
                        class={_className.value}
                        target={isOutside ? '_blank' : undefined}
                        rel={isOutside ? 'noreferrer noopener' : undefined}
                    >
                        {content}
                    </a>
                );
            }

            return (
                <router-link to={to.value} custom>
                    {({ href, navigate: handleNavigate }) => (
                        <a
                            href={href}
                            onClick={handleNavigate}
                            v-tooltip={_tooltip.value}
                            class={_className.value}
                        >
                            {content}
                        </a>
                    )}
                </router-link>
            );
        }

        return (
            <button
                // eslint-disable-next-line react/button-has-type
                type={htmlType.value}
                class={_className.value}
                disabled={disabled.value || loading.value}
                v-tooltip={!disabled.value ? _tooltip.value : undefined}
                onClick={handleClick}
            >
                {content}
            </button>
        );
    };
};

Button.props = {
    htmlType: {
        default: 'button',
        validator: (value) => (
            typeof value === 'string' &&
            ['button', 'submit', 'reset'].includes(value)
        ),
    },
    type: {
        type: String,
        validator: (value) => (
            [TYPES, Object.keys(PREDEFINED_TYPES)]
                .flat()
                .includes(value)
        ),
        default: 'default',
    },
    to: {
        type: [String, Object],
        default: undefined,
    },
    tooltip: {
        type: [String, Object],
        default: undefined,
    },
    icon: { type: String, default: undefined },
    size: {
        default: 'normal',
        validator: (value) => (
            typeof value === 'string' &&
            ['normal', 'large'].includes(value)
        ),
    },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    external: { type: Boolean, default: false },
};

Button.emits = ['click'];

export default Button;

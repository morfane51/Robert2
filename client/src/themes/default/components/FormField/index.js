import './index.scss';
import warning from 'warning';
import { computed, defineComponent } from '@vue/composition-api';
import Select from '@/themes/default/components/Select';
import Datepicker, { TYPES as DATEPICKER_TYPES } from '@/themes/default/components/Datepicker';
import SwitchToggle from '@/themes/default/components/SwitchToggle';
import Input, { TYPES as INPUT_TYPES } from '@/themes/default/components/Input';
import Textarea from '@/themes/default/components/Textarea';
import InputCopy from '@/themes/default/components/InputCopy';
import InputColor from '@/themes/default/components/InputColor';
import Color from '@/utils/color';

const TYPES = [
    ...DATEPICKER_TYPES,
    ...INPUT_TYPES,
    'color',
    'copy',
    'static',
    'select',
    'textarea',
    'switch',
    'custom',
];

// @vue/component
export default defineComponent({
    name: 'FormField',
    inject: {
        verticalForm: { default: false },
    },
    provide() {
        return {
            'input.disabled': computed(() => this.disabled),
            'input.invalid': computed(() => this.invalid),
        };
    },
    props: {
        label: { type: String, default: null },
        name: { type: String, default: undefined },
        type: {
            validator: (value) => TYPES.includes(value),
            default: 'text',
        },
        required: { type: Boolean, default: false },
        disabled: { type: [Boolean, String], default: false },
        help: { type: String, default: undefined },
        errors: { type: Array, default: null },
        placeholder: {
            type: [String, Boolean, Object],
            default: undefined,
        },
        value: {
            type: [String, Number, Date, Array, Boolean, Color],
            default: undefined,
        },
        rows: { type: Number, default: undefined },
        step: { type: Number, default: undefined },
        min: { type: Number, default: undefined },
        max: { type: Number, default: undefined },
        addon: { type: String, default: undefined },
        options: { type: Array, default: undefined },
        datepickerOptions: { type: Object, default: undefined },
    },
    computed: {
        invalid() {
            return this.errors && this.errors.length > 0;
        },
    },
    watch: {
        $slots: {
            immediate: true,
            handler() {
                this.validateProps();
            },
        },
        $props: {
            immediate: true,
            handler() {
                this.validateProps();
            },
        },
    },
    methods: {
        handleChange(newValue) {
            this.$emit('change', newValue);
        },

        handleInput(newValue) {
            this.$emit('input', newValue);
        },

        // ------------------------------------------------------
        // -
        // -    Méthodes internes
        // -
        // ------------------------------------------------------

        validateProps() {
            const hasChildren = this.$slots.default !== undefined;

            // - Fonction de rendu manquante pour un champ `custom`.
            warning(
                this.type !== 'custom' || hasChildren,
                '<FormField> La prop. `children` est manquante (ou vide) alors ' +
                `qu'elle est requise pour les champs \`custom\`.`,
            );

            // - Affiche un warning si si on a un champ non-`custom` et qu'une fonction de rendue a été fournie.
            warning(
                this.type === 'custom' || !hasChildren,
                '<FormField> La prop. `children` a été fournie pour un champ non ' +
                '`custom`, celle-ci ne sera pas utilisée.',
            );

            // - Affiche un warning si des props. sont passés à <FormField> alors qu'on est dans un champ `custom`.
            const customUselessProps = [
                'name', 'placeholder', 'value', 'rows', 'step',
                'min', 'max', 'addon', 'options', 'datepickerOptions',
            ];
            customUselessProps.forEach((customUselessProp) => {
                warning(
                    this.type !== 'custom' || this[customUselessProp] === undefined,
                    `<FormField> La prop. \`${customUselessProp}\` a été fournie pour ` +
                    'un champ "custom", celle-ci ne sera pas utilisée.',
                );
            });
        },
    },
    render() {
        const children = this.$slots.default;
        const {
            $t: __,
            $scopedSlots: slots,
            type,
            label,
            name,
            value,
            addon,
            placeholder,
            required,
            invalid,
            disabled,
            verticalForm: vertical,
            options,
            step,
            min,
            max,
            rows,
            help,
            handleChange,
            handleInput,
            datepickerOptions,
            errors,
        } = this;

        // - Placeholder.
        let _placeholder;
        if (placeholder !== undefined) {
            if (type === 'select') {
                _placeholder = typeof placeholder === 'boolean' ? placeholder : __(placeholder);
            } else if (placeholder) {
                _placeholder = placeholder === true ? label : __(placeholder);
            }
        }

        const classNames = ['FormField', `FormField--${type}`, {
            'FormField--vertical': !!vertical,
            'FormField--invalid': invalid,
        }];

        return (
            <div class={classNames}>
                {label && (
                    <label class="FormField__label">
                        {__(label)} {required && <span class="FormField__label__required">*</span>}
                    </label>
                )}
                <div class="FormField__field">
                    <div class="FormField__input-wrapper">
                        {INPUT_TYPES.includes(type) && (
                            <Input
                                class="FormField__input"
                                type={type}
                                step={step}
                                min={min}
                                max={max}
                                name={name}
                                autocomplete={type === 'password' ? 'new-password' : 'off'}
                                disabled={!!disabled}
                                invalid={invalid}
                                placeholder={_placeholder}
                                value={value}
                                addon={addon}
                                onInput={handleInput}
                                onChange={handleChange}
                            />
                        )}
                        {type === 'select' && (
                            <Select
                                class="FormField__input"
                                name={name}
                                options={options}
                                placeholder={_placeholder}
                                disabled={!!disabled}
                                invalid={invalid}
                                value={value}
                                onInput={handleInput}
                                onChange={handleChange}
                            />
                        )}
                        {type === 'textarea' && (
                            <Textarea
                                class="FormField__input"
                                name={name}
                                value={value}
                                rows={rows}
                                disabled={!!disabled}
                                invalid={invalid}
                                placeholder={_placeholder}
                                onInput={handleInput}
                                onChange={handleChange}
                            />
                        )}
                        {DATEPICKER_TYPES.includes(type) && (
                            <Datepicker
                                class="FormField__input"
                                name={name}
                                type={type}
                                value={value}
                                disabledDates={datepickerOptions?.disabled}
                                range={datepickerOptions?.range}
                                invalid={invalid}
                                disabled={disabled}
                                placeholder={_placeholder}
                                onInput={handleInput}
                                onChange={handleChange}
                            />
                        )}
                        {type === 'color' && (
                            <InputColor
                                class="FormField__input"
                                name={name}
                                disabled={!!disabled}
                                invalid={invalid}
                                value={value}
                                placeholder={placeholder}
                                onInput={handleInput}
                                onChange={handleChange}
                            />
                        )}
                        {type === 'switch' && (
                            <SwitchToggle
                                class="FormField__input"
                                name={name}
                                value={value ?? false}
                                disabled={disabled}
                                onInput={handleInput}
                                onChange={handleChange}
                            />
                        )}
                        {type === 'copy' && (
                            <InputCopy class="FormField__input" value={value} />
                        )}
                        {type === 'custom' && (
                            <div class="FormField__input">
                                {children}
                            </div>
                        )}
                        {type === 'static' && (
                            <p class="FormField__input">{value}</p>
                        )}
                    </div>
                    {invalid && (
                        <div class="FormField__error">
                            <span class="FormField__error__text">{errors[0]}</span>
                        </div>
                    )}
                    {!!(!invalid && (slots.help || help)) && (
                        <div class="FormField__help">{slots.help?.() ?? help}</div>
                    )}
                </div>
            </div>
        );
    },
});

import './index.scss';
import throttle from 'lodash/throttle';
import { defineComponent } from '@vue/composition-api';
import Color from '@/utils/color';

import type { PropType } from '@vue/composition-api';
import type { HexColorString, HsvaColorObject } from '@/utils/color';

type Props = {
    /** Couleur actuelle. */
    color: Color,
};

const ColorPickerGradient = defineComponent({
    name: 'ColorPickerGradient',
    props: {
        color: {
            type: Object as PropType<Required<Props>['color']>,
            required: true,
        },
    },
    computed: {
        hue(): number {
            return this.color.getHue();
        },

        hex(): HexColorString {
            return this.color.toHexString();
        },

        hsva(): HsvaColorObject {
            return this.color.toHsv();
        },
    },
    created() {
        this.debouncedMoveMarker = throttle(this.moveMarker.bind(this), 50);

        // - Binding.
        this.handleMouseUp = this.handleMouseUp.bind(this);
        this.handleMouseMove = this.handleMouseMove.bind(this);
        this.handleTouchMove = this.handleTouchMove.bind(this);
        this.handleTouchEnd = this.handleTouchEnd.bind(this);
    },
    beforeDestroy() {
        this.debouncedMoveMarker.cancel();

        document.removeEventListener('mousemove', this.handleMouseMove);
        document.removeEventListener('mouseup', this.handleMouseUp);
        document.removeEventListener('touchmove', this.handleTouchMove);
        document.removeEventListener('touchend', this.handleTouchEnd);
    },
    methods: {
        // ------------------------------------------------------
        // -
        // -    Handlers
        // -
        // ------------------------------------------------------

        handleClick(event: PointerEvent) {
            event.stopPropagation();

            this.moveMarker(event.pageX, event.pageY);
        },

        //
        // - Mouse events.
        //

        handleMouseMove(event: MouseEvent) {
            event.preventDefault();
            event.stopPropagation();

            this.debouncedMoveMarker(event.pageX, event.pageY);
        },

        handleMouseUp() {
            document.removeEventListener('mousemove', this.handleMouseMove);
        },

        handleMouseDown() {
            document.addEventListener('mousemove', this.handleMouseMove);
            document.addEventListener('mouseup', this.handleMouseUp);
        },

        //
        // - Touch events.
        //

        handleTouchMove(event: TouchEvent) {
            event.preventDefault();
            event.stopPropagation();

            const { pageX, pageY } = event.changedTouches[0];
            this.debouncedMoveMarker(pageX, pageY);
        },

        handleTouchEnd() {
            document.removeEventListener('touchmove', this.handleTouchMove);
        },

        handleTouchStart() {
            document.addEventListener('touchmove', this.handleTouchMove, { passive: false });
            document.addEventListener('touchend', this.handleTouchEnd);
        },

        // ------------------------------------------------------
        // -
        // -    Méthodes internes
        // -
        // ------------------------------------------------------

        moveMarker(pageX: number, pageY: number) {
            const containerPos = (this.$refs.containerRef as HTMLDivElement).getBoundingClientRect();
            let x = pageX - (containerPos.left + window.pageXOffset);
            let y = pageY - (containerPos.top + window.pageYOffset);

            x = x < 0 ? 0 : (x > containerPos.width ? containerPos.width : x);
            y = y < 0 ? 0 : (y > containerPos.height ? containerPos.height : y);

            const newColor = new Color({
                h: this.hsva.h,
                s: (x / containerPos.width),
                v: (1 - (y / containerPos.height)),
                a: this.hsva.a,
            });

            this.$emit('change', newColor);
            this.$refs.markerRef.focus();
        },
    },
    render() {
        const {
            hex,
            hue,
            hsva,
            handleClick,
            handleMouseDown,
            handleTouchStart,
        } = this;

        return (
            <div
                ref="containerRef"
                role="application"
                class="ColorPickerGradient"
                onClick={handleClick}
                onMousedown={handleMouseDown}
                onTouchstart={handleTouchStart}
                style={{
                    '--ColorPickerGradient--color': hex,
                    '--ColorPickerGradient--hue': hue,
                }}
            >
                <div
                    ref="markerRef"
                    class="ColorPickerGradient__marker"
                    onMousedown={handleMouseDown}
                    onTouchstart={handleTouchStart}
                    tabindex="0"
                    style={{
                        top: `${-(hsva.v * 100) + 100}%`,
                        left: `${hsva.s * 100}%`,
                    }}
                />
            </div>
        );
    },
});

export default ColorPickerGradient;

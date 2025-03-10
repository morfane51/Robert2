import './index.scss';
import moment from 'moment';
import { defineComponent } from '@vue/composition-api';
import Fragment from '@/components/Fragment';
import config from '@/globals/config';
import formatAmount from '@/utils/formatAmount';
import TagsList from '@/themes/default/components/TagsList';
import Attributes from './Attributes';

// @vue/component
const MaterialViewInfos = {
    name: 'MaterialViewInfos',
    props: {
        material: { required: true, type: Object },
    },
    data() {
        return {
            showBilling: config.billingMode !== 'none',
        };
    },
    computed: {
        createDate() {
            const { created_at: createdAt } = this.material;
            return createdAt ? moment(createdAt).format('L') : null;
        },

        updateDate() {
            const { updated_at: updatedAt } = this.material;
            return updatedAt ? moment(updatedAt).format('L') : null;
        },

        categoryName() {
            const { $t: __, material } = this;
            const { category_id: categoryId } = material;
            const categoryNameGetter = this.$store.getters['categories/categoryName'];
            return categoryNameGetter(categoryId) ?? __('not-categorized');
        },

        subCategoryName() {
            const { sub_category_id: subCategoryId } = this.material;
            const subCategoryNameGetter = this.$store.getters['categories/subCategoryName'];
            return subCategoryNameGetter(subCategoryId);
        },

        rentalPrice() {
            const { rental_price: rentalPrice } = this.material;
            return rentalPrice ? formatAmount(rentalPrice) : null;
        },

        replacementPrice() {
            const { replacement_price: replacementPrice } = this.material;
            return replacementPrice ? formatAmount(replacementPrice) : null;
        },

        hasPricingData() {
            return (
                this.rentalPrice !== null ||
                this.replacementPrice !== null
            );
        },

        queryCategory() {
            return { category: this.material.category_id ?? 'uncategorized' };
        },

        querySubCategory() {
            return {
                category: this.material.category_id ?? 'uncategorized',
                subCategory: this.material.sub_category_id,
            };
        },
    },
    mounted() {
        this.$store.commit('setPageSubTitle', this.material.name);
    },
    render() {
        const {
            $t: __,
            queryCategory,
            querySubCategory,
            categoryName,
            subCategoryName,
            showBilling,
            hasPricingData,
            rentalPrice,
            replacementPrice,
            createDate,
            updateDate,
            material,
        } = this;

        const {
            reference,
            name,
            description,
            stock_quantity: stockQuantity,
            out_of_order_quantity: outOfOrderQuantity,
            is_hidden_on_bill: isHiddenOnBill,
            is_discountable: isDiscountable,
            attributes,
            picture,
            note,
            tags,
        } = material;

        return (
            <div class="MaterialViewInfos">
                <section class="MaterialViewInfos__main">
                    <h2>{reference}</h2>
                    <h3>
                        <router-link to={{ name: 'materials', query: queryCategory }}>
                            {categoryName}
                        </router-link>
                        {!!subCategoryName && (
                            <Fragment>
                                {' / '}
                                <router-link to={{ name: 'materials', query: querySubCategory }}>
                                    {subCategoryName}
                                </router-link>
                            </Fragment>
                        )}
                        {' / '}
                        {name}
                    </h3>
                    <p>{description}</p>
                    <h3>{__('quantities')}</h3>
                    <ul>
                        <li class="MaterialViewInfos__stock-quantity">
                            {__('stock-items-count', { count: stockQuantity || 0 })}
                        </li>
                        {outOfOrderQuantity > 0 && (
                            <li class="MaterialViewInfos__out-of-order">
                                {__('out-of-order-items-count', { count: outOfOrderQuantity })}
                            </li>
                        )}
                    </ul>
                    {(showBilling && hasPricingData) && (
                        <div class="MaterialViewInfos__billing">
                            <h3>{__('prices')}</h3>
                            <ul>
                                {rentalPrice !== null && (
                                    <li class="MaterialViewInfos__rental-price">
                                        {__('value-per-day', { value: rentalPrice })}
                                    </li>
                                )}
                                {replacementPrice !== null && (
                                    <li>
                                        {__('replacement-price')} {replacementPrice}
                                    </li>
                                )}
                            </ul>
                            <h3>{__('billing')}</h3>
                            {isHiddenOnBill && (
                                <p>{__('material-not-displayed-on-invoice')}</p>
                            )}
                            {isDiscountable && (
                                <p>{__('material-is-discountable')}</p>
                            )}
                        </div>
                    )}
                    <Attributes attributes={attributes} />
                    {!!note && (
                        <div class="MaterialViewInfos__notes">
                            <h3>{__('notes')}</h3>
                            <p class="MaterialViewInfos__notes">{note}</p>
                        </div>
                    )}
                </section>
                {!!picture && (
                    <section class="MaterialViewInfos__picture">
                        <a
                            href={picture}
                            target="blank"
                            title={__('page.material-view.infos.click-to-open-image')}
                        >
                            <img
                                src={picture}
                                alt={name}
                                class="MaterialViewInfos__picture__img"
                            />
                        </a>
                    </section>
                )}
                <section class="MaterialViewInfos__extras">
                    <div class="MaterialViewInfos__categories">
                        <p>{__('category')}: <strong>{categoryName}</strong></p>
                        {!!subCategoryName && (
                            <p>{__('sub-category')}: <strong>{subCategoryName}</strong></p>
                        )}
                    </div>
                    {(tags && tags.length > 0) && <TagsList tags={tags} />}
                    {(!!createDate || !!updateDate) && (
                        <div class="MaterialViewInfos__dates">
                            {createDate && <p>{__('created-at')} {createDate}</p>}
                            {updateDate && <p>{__('updated-at')} {updateDate}</p>}
                        </div>
                    )}
                </section>
            </div>
        );
    },
};

export default defineComponent(MaterialViewInfos);

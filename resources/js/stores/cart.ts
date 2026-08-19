import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { Cart, CartResponse, Product } from '../types';

interface CartState {
    cart: Cart | null;
    loading: boolean;
    error: string | null;
}

export const useCartStore = defineStore('cart', {
    state: (): CartState => ({
        cart: null,
        loading: false,
        error: null,
    }),

    getters: {
        items: (state) => state.cart?.items ?? [],
        count: (state) => state.cart?.items.reduce((sum, item) => sum + item.quantity, 0) ?? 0,
        subtotal: (state) => Number(state.cart?.subtotal ?? 0),
        discount: (state) => Number(state.cart?.discount ?? 0),
        total: (state) => Number(state.cart?.total ?? 0),
        cartQtyMap: (state) => {
            const map: Record<string, number> = {};
            state.cart?.items.forEach((item) => {
                map[item.product_id] = item.quantity;
            });
            return map;
        },
    },

    actions: {
        async fetchCart() {
            return this.withLoading(async () => {
                const { data } = await http.get<CartResponse>('/cart');
                this.cart = data.cart;
            });
        },

        async updateCart(payload: { customer_id?: string | null; discount?: number }) {
            return this.withLoading(async () => {
                const { data } = await http.patch<CartResponse>('/cart', payload);
                this.cart = data.cart;
            });
        },

        async addProduct(product: Product, quantity = 1) {
            return this.withLoading(async () => {
                const { data } = await http.post<CartResponse>('/cart/items', {
                    product_id: product.id,
                    quantity,
                });
                this.cart = data.cart;
            });
        },

        async updateItem(cartItemId: string, quantity: number) {
            return this.withLoading(async () => {
                const { data } = await http.patch<CartResponse>(`/cart/items/${cartItemId}`, { quantity });
                this.cart = data.cart;
            });
        },

        async updateProductQuantity(productId: string, quantity: number) {
            const item = this.cart?.items.find((cartItem) => cartItem.product_id === productId);

            if (!item) {
                return false;
            }

            return this.updateItem(item.id, quantity);
        },

        async removeItem(cartItemId: string) {
            return this.withLoading(async () => {
                const { data } = await http.delete<CartResponse>(`/cart/items/${cartItemId}`);
                this.cart = data.cart;
            });
        },

        async removeProduct(productId: string) {
            const item = this.cart?.items.find((cartItem) => cartItem.product_id === productId);

            if (!item) {
                return false;
            }

            return this.removeItem(item.id);
        },

        async clearCart() {
            return this.withLoading(async () => {
                const { data } = await http.delete<CartResponse>('/cart');
                this.cart = data.cart;
            });
        },

        async withLoading(callback: () => Promise<void>): Promise<boolean> {
            this.loading = true;
            this.error = null;

            try {
                await callback();
                return true;
            } catch (error) {
                this.error = extractErrorMessage(error);
                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});

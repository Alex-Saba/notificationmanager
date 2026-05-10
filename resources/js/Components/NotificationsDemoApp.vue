<template>
    <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
        <header class="mb-8 flex flex-col gap-4 rounded-[32px] border border-[#DCDBDA] bg-white px-6 py-6 shadow-[0_18px_48px_rgba(0,0,0,0.06)] md:flex-row md:items-end md:justify-between">
            <div class="max-w-3xl">
                <p class="mb-3 inline-flex items-center rounded-full bg-[#DCDBDA] px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-700">
                    Notifications in-app
                </p>
                <h1 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-slate-950 md:text-5xl">
                    Démo notifications
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-600 md:text-base">
                    Créez des notifications de démonstration, filtrez-les par type, date ou statut de lecture, puis testez le marquage lu / non lu.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-[28px] border border-[#DCDBDA] bg-[#DCDBDA] px-5 py-4 text-slate-900 shadow-[0_12px_32px_rgba(0,0,0,0.05)]">
                <div class="grid gap-1">
                    <p class="text-xs uppercase tracking-[0.22em] text-slate-700">Centre</p>
                    <p class="text-3xl font-semibold">{{ stats.total }}</p>
                    <p class="text-sm text-slate-600">{{ stats.unread }} non lues</p>
                </div>
                <a
                    :href="routes.templates.page"
                    class="inline-flex rounded-full border border-white bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-[#f5f5f5]"
                >
                    Retour templates
                </a>
            </div>
        </header>

        <div
            v-if="flashMessage"
            class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-700"
        >
            <span>{{ flashMessage }}</span>
            <button
                type="button"
                class="rounded-full bg-[#DCDBDA] px-3 py-1 text-xs font-semibold text-slate-700 transition hover:bg-[#d2d1d0]"
                @click="flashMessage = ''"
            >
                Fermer
            </button>
        </div>

        <div
            v-if="globalError"
            class="mb-6 rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-700"
        >
            {{ globalError }}
        </div>

        <main class="grid flex-1 gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside class="space-y-6">
                <section class="rounded-[28px] border border-[#DCDBDA] bg-white p-5 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                    <div class="mb-4">
                        <h2 class="font-['Space_Grotesk'] text-xl font-bold text-slate-950">Nouvelle notification</h2>
                        <p class="mt-1 text-sm text-slate-600">Cette action alimente directement la table `communications` sur le canal `database`.</p>
                    </div>

                    <form class="space-y-4" @submit.prevent="createNotification">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="type">Type</label>
                            <input
                                id="type"
                                v-model="form.type"
                                type="text"
                                placeholder="billing-reminder"
                                class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            >
                            <p v-if="errors.type" class="mt-2 text-sm text-slate-600">{{ errors.type[0] }}</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="title">Titre</label>
                            <input
                                id="title"
                                v-model="form.title"
                                type="text"
                                placeholder="Paiement en attente"
                                class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            >
                            <p v-if="errors.title" class="mt-2 text-sm text-slate-600">{{ errors.title[0] }}</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="message">Message</label>
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="5"
                                placeholder="Le paiement de la facture #428 arrive a echeance demain."
                                class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            />
                            <p v-if="errors.message" class="mt-2 text-sm text-slate-600">{{ errors.message[0] }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700" for="recipient_address">Destinataire</label>
                                <input
                                    id="recipient_address"
                                    v-model="form.recipient_address"
                                    type="text"
                                    placeholder="alex@example.test"
                                    class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700" for="date">Date</label>
                                <input
                                    id="date"
                                    v-model="form.date"
                                    type="date"
                                    class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                >
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-3 rounded-2xl border border-[#DCDBDA] bg-[#f8f8f8] px-4 py-3 text-sm text-slate-700">
                            <input
                                v-model="form.read"
                                type="checkbox"
                                class="h-4 w-4 rounded border-[#DCDBDA] text-slate-700"
                            >
                            <span>Créer directement comme lue</span>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-2xl bg-[#DCDBDA] px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-[#d2d1d0] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isSubmitting"
                        >
                            {{ isSubmitting ? 'Création...' : 'Créer la notification' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-[28px] border border-[#DCDBDA] bg-white p-5 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                    <div class="mb-4">
                        <h2 class="font-['Space_Grotesk'] text-xl font-bold text-slate-950">Filtres</h2>
                        <p class="mt-1 text-sm text-slate-600">Travaillez comme sur une vraie boite de notifications.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="filter-type">Type</label>
                            <input
                                id="filter-type"
                                v-model="filters.type"
                                type="text"
                                placeholder="billing-reminder"
                                class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                @input="fetchNotifications"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="filter-date">Date</label>
                            <input
                                id="filter-date"
                                v-model="filters.date"
                                type="date"
                                class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                @change="fetchNotifications"
                            >
                        </div>

                        <label class="inline-flex items-center gap-3 rounded-2xl border border-[#DCDBDA] bg-[#f8f8f8] px-4 py-3 text-sm text-slate-700">
                            <input
                                v-model="filters.unread"
                                type="checkbox"
                                class="h-4 w-4 rounded border-[#DCDBDA] text-slate-700"
                                @change="fetchNotifications"
                            >
                            <span>Afficher seulement les non lues</span>
                        </label>

                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-2xl border border-[#DCDBDA] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[#f7f7f7]"
                            @click="resetFilters"
                        >
                            Reinitialiser
                        </button>
                    </div>
                </section>
            </aside>

            <section class="grid gap-6 lg:grid-cols-[minmax(0,340px)_minmax(0,1fr)]">
                <div class="rounded-[28px] border border-[#DCDBDA] bg-white p-5 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="font-['Space_Grotesk'] text-xl font-bold text-slate-950">Flux</h2>
                            <p class="mt-1 text-sm text-slate-600">Selectionnez une notification pour voir son detail.</p>
                        </div>
                        <span class="rounded-full bg-[#DCDBDA] px-3 py-1 text-xs font-semibold text-slate-700">
                            {{ notifications.length }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <button
                            v-for="notification in notifications"
                            :key="notification.id"
                            type="button"
                            class="block w-full rounded-3xl border p-4 text-left transition"
                            :class="selectedNotification?.id === notification.id
                                ? 'border-[#DCDBDA] bg-[#DCDBDA] shadow-[0_12px_32px_rgba(0,0,0,0.05)]'
                                : 'border-[#DCDBDA] bg-white hover:bg-[#f7f7f7]'"
                            @click="selectNotification(notification)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-slate-900">{{ notification.title }}</h3>
                                    <p class="mt-1 text-xs text-slate-600">{{ notification.type }} · {{ formatDate(notification.created_at) }}</p>
                                </div>
                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]"
                                    :class="notification.read_at ? 'bg-white text-slate-600' : 'bg-white text-slate-900'"
                                >
                                    {{ notification.read_at ? 'lu' : 'non lu' }}
                                </span>
                            </div>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-700">{{ notification.message }}</p>
                        </button>

                        <div v-if="isLoading" class="rounded-3xl border border-dashed border-[#DCDBDA] bg-white px-4 py-8 text-center text-sm text-slate-500">
                            Chargement des notifications...
                        </div>

                        <div v-else-if="notifications.length === 0" class="rounded-3xl border border-dashed border-[#DCDBDA] bg-white px-4 py-8 text-center text-sm text-slate-500">
                            Aucune notification pour ces filtres.
                        </div>
                    </div>
                </div>

                <div class="rounded-[32px] border border-[#DCDBDA] bg-white p-6 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                    <template v-if="selectedNotification">
                        <div class="mb-6 flex flex-col gap-4 border-b border-[#DCDBDA] pb-6 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.22em] text-slate-600">Detail</p>
                                <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold text-slate-950">{{ selectedNotification.title }}</h2>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-full bg-[#DCDBDA] px-3 py-1 text-xs font-semibold text-slate-700">{{ selectedNotification.type }}</span>
                                    <span class="rounded-full bg-[#f7f7f7] px-3 py-1 text-xs text-slate-700">{{ selectedNotification.event_key }}</span>
                                    <span class="rounded-full bg-[#f7f7f7] px-3 py-1 text-xs text-slate-700">{{ selectedNotification.status }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 md:w-60">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-[22px] bg-[#DCDBDA] px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-[#d2d1d0]"
                                    @click="toggleRead(selectedNotification)"
                                >
                                    {{ selectedNotification.read_at ? 'Marquer non lue' : 'Marquer lue' }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-[22px] border border-[#DCDBDA] bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[#f7f7f7]"
                                    @click="deleteNotification(selectedNotification)"
                                >
                                    Supprimer
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-[24px] border border-[#DCDBDA] bg-[#fafafa] p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Message</p>
                                <p class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-800">{{ selectedNotification.message }}</p>
                            </div>
                            <div class="rounded-[24px] border border-[#DCDBDA] bg-[#fafafa] p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Meta</p>
                                <div class="mt-3 space-y-2 text-sm text-slate-700">
                                    <p><span class="font-semibold">Destinataire :</span> {{ selectedNotification.recipient_address || 'Non renseigne' }}</p>
                                    <p><span class="font-semibold">Type cible :</span> {{ selectedNotification.recipient_type || 'Non renseigne' }}</p>
                                    <p><span class="font-semibold">Création :</span> {{ formatDate(selectedNotification.created_at) }}</p>
                                    <p><span class="font-semibold">Lecture :</span> {{ selectedNotification.read_at ? formatDate(selectedNotification.read_at) : 'Non lue' }}</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div v-else class="flex h-full items-center justify-center rounded-[28px] border border-dashed border-[#DCDBDA] bg-white p-8 text-center text-slate-500">
                        Selectionnez une notification pour afficher son detail.
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const ui = window.__ACL_COMMUNICATIONS_UI__ ?? {};
const routes = ui.routes ?? {
    templates: {
        page: '/communications/templates',
    },
    notifications: {
        page: '/communications/notifications',
        index: '/communications/api/notifications',
        store: '/communications/api/notifications',
        showPattern: '/communications/api/notifications/__NOTIFICATION__',
        markReadPattern: '/communications/api/notifications/__NOTIFICATION__/read',
        markUnreadPattern: '/communications/api/notifications/__NOTIFICATION__/unread',
        destroyPattern: '/communications/api/notifications/__NOTIFICATION__',
    },
};
const notifications = ref([]);
const selectedNotification = ref(null);
const isLoading = ref(true);
const isSubmitting = ref(false);
const globalError = ref('');
const flashMessage = ref('');
const stats = ref({
    total: 0,
    unread: 0,
    types: [],
});
const filters = ref({
    unread: false,
    type: '',
    date: '',
});
const form = ref({
    type: '',
    title: '',
    message: '',
    recipient_address: '',
    date: '',
    read: false,
});
const errors = ref({});

async function fetchNotifications() {
    isLoading.value = true;
    globalError.value = '';

    try {
        const { data } = await window.axios.get(routes.notifications.index, {
            params: {
                unread: filters.value.unread ? 1 : 0,
                type: filters.value.type || undefined,
                date: filters.value.date || undefined,
            },
        });

        notifications.value = data.notifications ?? [];
        stats.value = data.stats ?? stats.value;
        selectedNotification.value = notifications.value.find((item) => item.id === selectedNotification.value?.id)
            ?? notifications.value[0]
            ?? null;
    } catch (error) {
        globalError.value = 'Impossible de charger les notifications.';
    } finally {
        isLoading.value = false;
    }
}

async function createNotification() {
    isSubmitting.value = true;
    errors.value = {};
    globalError.value = '';

    try {
        const { data } = await window.axios.post(routes.notifications.store, form.value);
        flashMessage.value = data.message;
        form.value = {
            type: '',
            title: '',
            message: '',
            recipient_address: '',
            date: '',
            read: false,
        };
        await fetchNotifications();
        selectedNotification.value = notifications.value.find((item) => item.id === data.notification.id) ?? selectedNotification.value;
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            return;
        }

        globalError.value = 'La création de la notification a échoué.';
    } finally {
        isSubmitting.value = false;
    }
}

function selectNotification(notification) {
    selectedNotification.value = notification;
}

async function toggleRead(notification) {
    try {
        const endpoint = notification.read_at
            ? replaceRouteParam(routes.notifications.markUnreadPattern, '__NOTIFICATION__', notification.id)
            : replaceRouteParam(routes.notifications.markReadPattern, '__NOTIFICATION__', notification.id);
        const { data } = await window.axios.patch(endpoint);
        flashMessage.value = data.message;
        replaceNotification(data.notification);
        stats.value.unread = notifications.value.filter((item) => !item.read_at).length;
    } catch (error) {
        globalError.value = 'Impossible de modifier le statut de lecture.';
    }
}

async function deleteNotification(notification) {
    try {
        const { data } = await window.axios.delete(replaceRouteParam(routes.notifications.destroyPattern, '__NOTIFICATION__', notification.id));
        flashMessage.value = data.message;
        notifications.value = notifications.value.filter((item) => item.id !== notification.id);
        selectedNotification.value = notifications.value[0] ?? null;
        stats.value.total = notifications.value.length;
        stats.value.unread = notifications.value.filter((item) => !item.read_at).length;
    } catch (error) {
        globalError.value = 'Impossible de supprimer la notification.';
    }
}

function replaceNotification(notification) {
    notifications.value = notifications.value.map((item) => item.id === notification.id ? notification : item);
    selectedNotification.value = notification;
}

function resetFilters() {
    filters.value = {
        unread: false,
        type: '',
        date: '',
    };
    fetchNotifications();
}

function formatDate(value) {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function replaceRouteParam(pattern, placeholder, value) {
    return pattern.replace(placeholder, String(value));
}

onMounted(fetchNotifications);
</script>

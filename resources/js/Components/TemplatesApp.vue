<template>
    <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
        <header class="mb-8 flex flex-col gap-4 rounded-[32px] border border-[#DCDBDA] bg-white px-6 py-6 shadow-[0_18px_48px_rgba(0,0,0,0.06)] md:flex-row md:items-end md:justify-between">
            <div class="max-w-3xl">
                <p class="mb-3 inline-flex items-center rounded-full bg-[#DCDBDA] px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-700">
                    Communications templates
                </p>
                <h1 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-slate-950 md:text-5xl">
                    Bibliothèque templates
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-600 md:text-base">
                    Consultez les templates actifs, leurs event keys et les règles associées.
                </p>
            </div>

            <a
                :href="routes.notifications.page"
                class="inline-flex items-center justify-center rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[#f7f7f7]"
            >
                Notifications
            </a>
        </header>

        <div
            v-if="globalError"
            class="mb-6 rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-700"
        >
            {{ globalError }}
        </div>

        <main class="grid flex-1 gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <aside class="rounded-[32px] border border-[#DCDBDA] bg-white p-5 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                <div class="mb-5">
                    <p class="text-xs uppercase tracking-[0.22em] text-slate-500">Bibliothèque</p>
                    <p class="mt-1 text-3xl font-semibold text-slate-950">{{ templates.length }}</p>
                    <p class="text-sm text-slate-600">templates disponibles</p>
                </div>

                <input
                    v-model="search"
                    type="search"
                    placeholder="Rechercher un template"
                    class="mb-4 w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                >

                <div v-if="isLoading" class="rounded-2xl border border-dashed border-[#DCDBDA] p-5 text-sm text-slate-500">
                    Chargement des templates...
                </div>

                <div v-else-if="filteredTemplates.length === 0" class="rounded-2xl border border-dashed border-[#DCDBDA] p-5 text-sm text-slate-500">
                    Aucun template trouve.
                </div>

                <div v-else class="space-y-3">
                    <button
                        v-for="template in filteredTemplates"
                        :key="template.id"
                        type="button"
                        class="w-full rounded-2xl border px-4 py-3 text-left transition"
                        :class="activeTemplate?.id === template.id ? 'border-slate-400 bg-[#f7f7f7]' : 'border-[#DCDBDA] bg-white hover:bg-[#fafafa]'"
                        @click="selectTemplate(template)"
                    >
                        <span class="block text-sm font-semibold text-slate-900">{{ template.display_name }}</span>
                        <span class="mt-1 block break-words text-xs text-slate-500">{{ template.event_key }}</span>
                    </button>
                </div>
            </aside>

            <section class="rounded-[32px] border border-[#DCDBDA] bg-white p-6 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                <template v-if="activeTemplate">
                    <div class="mb-6 flex flex-col gap-4 border-b border-[#DCDBDA] pb-6 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-500">{{ activeTemplate.channel || 'multi-channel' }}</p>
                            <h2 class="mt-2 font-['Space_Grotesk'] text-2xl font-bold text-slate-950">
                                {{ activeTemplate.display_name }}
                            </h2>
                            <p class="mt-2 break-words text-sm text-slate-600">{{ activeTemplate.key }}</p>
                        </div>

                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                            :class="activeTemplate.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ activeTemplate.active ? 'Actif' : 'Inactif' }}
                        </span>
                    </div>

                    <dl class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-[#DCDBDA] bg-[#fafafa] p-4">
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Event key</dt>
                            <dd class="mt-2 break-words text-sm font-semibold text-slate-900">{{ activeTemplate.rule.event_key || activeTemplate.event_key }}</dd>
                        </div>
                        <div class="rounded-2xl border border-[#DCDBDA] bg-[#fafafa] p-4">
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Canaux</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-900">{{ channelsLabel }}</dd>
                        </div>
                        <div class="rounded-2xl border border-[#DCDBDA] bg-[#fafafa] p-4">
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Priorité</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-900">{{ activeTemplate.rule.priority ?? '-' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-[#DCDBDA] bg-[#fafafa] p-4">
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Delai</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-900">{{ activeTemplate.rule.delay ?? 0 }} min</dd>
                        </div>
                    </dl>

                    <div class="rounded-[28px] border border-[#DCDBDA] bg-white p-4">
                        <pre class="overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm leading-7 text-slate-700">{{ activeTemplate.content }}</pre>
                    </div>
                </template>

                <div v-else class="flex h-full min-h-96 items-center justify-center rounded-[28px] border border-dashed border-[#DCDBDA] bg-white p-8 text-center text-slate-500">
                    Selectionnez un template pour afficher son detail.
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const ui = window.__ACL_COMMUNICATIONS_UI__ ?? {};
const routes = ui.routes ?? {
    templates: {
        index: '/communications/api/templates',
    },
    notifications: {
        page: '/communications/notifications',
    },
};

const templates = ref([]);
const activeTemplate = ref(null);
const search = ref('');
const isLoading = ref(true);
const globalError = ref('');

const filteredTemplates = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return templates.value;
    }

    return templates.value.filter((template) => [
        template.display_name,
        template.key,
        template.event_key,
        template.rule?.event_key,
        template.excerpt,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(query));
});

const channelsLabel = computed(() => {
    const channels = activeTemplate.value?.rule?.channels ?? [];

    return channels.length > 0 ? channels.join(', ') : activeTemplate.value?.channel ?? '-';
});

async function fetchTemplates() {
    isLoading.value = true;
    globalError.value = '';

    try {
        const { data } = await window.axios.get(routes.templates.index);
        templates.value = data.templates ?? [];
        const selectedId = Number.parseInt(new URLSearchParams(window.location.search).get('template') ?? '', 10);
        activeTemplate.value = templates.value.find((template) => template.id === selectedId) ?? templates.value[0] ?? null;
        syncListUrl();
    } catch (error) {
        globalError.value = 'Impossible de charger les templates pour le moment.';
    } finally {
        isLoading.value = false;
    }
}

function selectTemplate(template) {
    activeTemplate.value = template;
    syncListUrl();
}

function syncListUrl() {
    const nextUrl = new URL(window.location.href);

    if (activeTemplate.value?.id) {
        nextUrl.searchParams.set('template', String(activeTemplate.value.id));
    } else {
        nextUrl.searchParams.delete('template');
    }

    window.history.replaceState({}, '', nextUrl);
}

onMounted(fetchTemplates);
</script>

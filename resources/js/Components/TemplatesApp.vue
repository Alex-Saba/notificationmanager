<template>
    <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
        <header class="mb-8 flex flex-col gap-4 rounded-[32px] border border-[#DCDBDA] bg-white px-6 py-6 shadow-[0_18px_48px_rgba(0,0,0,0.06)] md:flex-row md:items-end md:justify-between">
            <div class="max-w-3xl">
                <p class="mb-3 inline-flex items-center rounded-full bg-[#DCDBDA] px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-700">
                    Communications templates
                </p>
                <h1 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-slate-950 md:text-5xl">
                    {{ pageTitle }}
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-600 md:text-base">
                    {{ pageDescription }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-[28px] border border-[#DCDBDA] bg-[#DCDBDA] px-5 py-4 text-slate-900 shadow-[0_12px_32px_rgba(0,0,0,0.05)]">
                <div class="mr-3 grid gap-1">
                    <p class="text-xs uppercase tracking-[0.22em] text-slate-700">Bibliotheque</p>
                    <p class="text-3xl font-semibold">{{ templates.length }}</p>
                    <p class="text-sm text-slate-600">templates disponibles</p>
                </div>

                <a
                    v-if="viewMode === 'list'"
                    :href="routes.templates.createPage"
                    class="inline-flex rounded-full border border-white bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-[#f5f5f5]"
                >
                    Nouveau template
                </a>
                <a
                    v-else
                    :href="routes.templates.page"
                    class="inline-flex rounded-full border border-white bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-[#f5f5f5]"
                >
                    Retour a la liste
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

        <main v-if="isEditorView" class="grid flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <section class="rounded-[32px] border border-[#DCDBDA] bg-white p-6 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                <div class="mb-6">
                    <h2 class="font-['Space_Grotesk'] text-2xl font-bold text-slate-950">
                        {{ viewMode === 'create' ? 'Nouveau template' : 'Template et regle liee' }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ viewMode === 'create'
                            ? "Chaque creation ajoute aussi sa regle de diffusion. La cle template est geree automatiquement."
                            : "Le contenu du template et les parametres de la regle sont separes. Vous pouvez modifier uniquement la regle si besoin." }}
                    </p>
                </div>

                <form class="space-y-6" @submit.prevent="submitEditor">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-slate-700">Nom du template</label>
                            <input
                                id="name"
                                ref="nameInput"
                                v-model="form.name"
                                type="text"
                                placeholder="Ex: Relance paiement"
                                class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            >
                            <p v-if="errors.name" class="mt-2 text-sm text-slate-600">{{ errors.name[0] }}</p>
                        </div>

                        <div>
                            <p class="mb-2 block text-sm font-medium text-slate-700">Cle template</p>
                            <div class="w-full rounded-2xl border border-[#DCDBDA] bg-[#f7f7f7] px-4 py-3 text-sm text-slate-900">
                                {{ generatedTemplateKey }}
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Generee automatiquement a partir du nom du template.</p>
                            <p v-if="errors.key" class="mt-2 text-sm text-slate-600">{{ errors.key[0] }}</p>
                        </div>

                        <div>
                            <label for="event_key" class="mb-2 block text-sm font-medium text-slate-700">Event key</label>
                            <select
                                id="event_key"
                                v-model="form.rule.event_key"
                                class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            >
                                <option value="">Selectionnez un event key</option>
                                <option
                                    v-for="eventKey in eventKeyOptions"
                                    :key="eventKey.key"
                                    :value="eventKey.key"
                                >
                                    {{ eventKey.label }}
                                </option>
                            </select>
                            <p class="mt-2 text-xs text-slate-500">Liste issue du projet principal. La cle selectionnee reste stable meme si le contenu change.</p>
                            <p v-if="selectedEventKeyDescription" class="mt-2 text-xs text-slate-500">{{ selectedEventKeyDescription }}</p>
                            <p v-if="errors.event_key" class="mt-2 text-sm text-slate-600">{{ errors.event_key[0] }}</p>
                        </div>
                    </div>

                    <div class="rounded-[28px] border border-[#DCDBDA] bg-[#fafafa] p-5">
                        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="font-['Space_Grotesk'] text-xl font-bold text-slate-950">Contenu du template</h3>
                                <p class="text-sm text-slate-600">Le contenu HTML est editable en WYSIWYG avec insertion de tags.</p>
                            </div>
                            <span class="text-xs text-slate-500">Editeur WYSIWYG</span>
                        </div>

                        <div class="overflow-hidden rounded-2xl border border-[#DCDBDA] bg-white">
                            <div class="flex flex-wrap gap-2 border-b border-[#DCDBDA] bg-[#f7f7f7] p-3">
                                <button
                                    v-for="action in editorActions"
                                    :key="action.label"
                                    type="button"
                                    class="inline-flex min-w-10 items-center justify-center rounded-xl border border-[#DCDBDA] bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-[#f3f3f3]"
                                    @click="runEditorAction(action)"
                                >
                                    {{ action.label }}
                                </button>
                            </div>

                            <div
                                id="content"
                                ref="editorRef"
                                contenteditable="true"
                                class="wysiwyg-editor min-h-80 w-full bg-white px-4 py-4 text-sm leading-7 text-slate-900 outline-none"
                                data-placeholder="Commencez a rediger votre template ici..."
                                @input="syncEditorContent"
                            />
                        </div>

                        <p class="mt-2 text-xs text-slate-500">
                            Vous pouvez inserer des tags comme <span class="font-medium text-slate-700">{{ templateExampleName }}</span> ou <span class="font-medium text-slate-700">{{ templateExampleDate }}</span>.
                        </p>
                        <p v-if="errors.content" class="mt-2 text-sm text-slate-600">{{ errors.content[0] }}</p>
                    </div>

                    <div class="rounded-[28px] border border-[#DCDBDA] bg-[#fafafa] p-5">
                        <div class="mb-4">
                            <h3 class="font-['Space_Grotesk'] text-xl font-bold text-slate-950">Regle de diffusion</h3>
                            <p class="mt-1 text-sm text-slate-600">Cette regle peut etre ajustee sans toucher au contenu du template.</p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700">Canaux</label>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="channel in channelOptions"
                                        :key="channel.value"
                                        class="inline-flex items-center gap-2 rounded-full border border-[#DCDBDA] bg-white px-3 py-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="form.rule.channels"
                                            :value="channel.value"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-[#DCDBDA] text-slate-700"
                                        >
                                        <span>{{ channel.label }}</span>
                                    </label>
                                </div>
                                <p v-if="errors.channels" class="mt-2 text-sm text-slate-600">{{ errors.channels[0] }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700">Fallback</label>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="channel in channelOptions"
                                        :key="`fallback-${channel.value}`"
                                        class="inline-flex items-center gap-2 rounded-full border border-[#DCDBDA] bg-white px-3 py-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="form.rule.fallback"
                                            :value="channel.value"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-[#DCDBDA] text-slate-700"
                                        >
                                        <span>{{ channel.label }}</span>
                                    </label>
                                </div>
                                <p v-if="errors.fallback" class="mt-2 text-sm text-slate-600">{{ errors.fallback[0] }}</p>
                            </div>

                            <div>
                                <label for="priority" class="mb-2 block text-sm font-medium text-slate-700">Priorite</label>
                                <input
                                    id="priority"
                                    v-model.number="form.rule.priority"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                >
                                <p v-if="errors.priority" class="mt-2 text-sm text-slate-600">{{ errors.priority[0] }}</p>
                            </div>

                            <div>
                                <label for="delay" class="mb-2 block text-sm font-medium text-slate-700">Delai</label>
                                <input
                                    id="delay"
                                    v-model.number="form.rule.delay"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                                >
                                <p class="mt-2 text-xs text-slate-500">Valeur en minutes.</p>
                                <p v-if="errors.delay" class="mt-2 text-sm text-slate-600">{{ errors.delay[0] }}</p>
                            </div>
                        </div>

                        <label class="mt-5 inline-flex items-center gap-3 rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-700">
                            <input
                                v-model="form.rule.active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-[#DCDBDA] text-slate-700"
                            >
                            <span>Regle active</span>
                        </label>
                        <p v-if="errors.active" class="mt-2 text-sm text-slate-600">{{ errors.active[0] }}</p>
                    </div>

                    <div class="flex flex-col gap-3 xl:flex-row">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-[#DCDBDA] px-5 py-3 text-sm font-semibold text-slate-800 transition hover:-translate-y-0.5 hover:bg-[#d2d1d0] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isSubmitting || isLoadingEditor"
                        >
                            {{ submitLabel }}
                        </button>
                        <button
                            v-if="viewMode === 'edit'"
                            type="button"
                            class="inline-flex items-center justify-center rounded-2xl border border-[#DCDBDA] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[#f7f7f7] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isSubmitting || isLoadingEditor"
                            @click="updateRuleOnly"
                        >
                            Enregistrer seulement la regle
                        </button>
                        <a
                            :href="routes.templates.page"
                            class="inline-flex items-center justify-center rounded-2xl border border-[#DCDBDA] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[#f7f7f7]"
                        >
                            Annuler
                        </a>
                    </div>
                </form>
            </section>

            <aside class="space-y-6">
                <div class="rounded-[28px] border border-[#DCDBDA] bg-white p-5 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                    <h3 class="font-['Space_Grotesk'] text-lg font-bold text-slate-950">Guide rapide</h3>
                    <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <p>Chaque cle template identifie un seul template reutilisable.</p>
                        <p>L'event key doit rester stable, meme si vous changez la redaction ou la langue.</p>
                        <p>La regle active pilote la diffusion sans obliger a retoucher le contenu HTML.</p>
                    </div>
                </div>

                <div class="rounded-[28px] border border-[#DCDBDA] bg-white p-5 text-slate-900 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs uppercase tracking-[0.22em] text-slate-600">Tags disponibles</p>
                        <span class="rounded-full bg-[#DCDBDA] px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                            {{ totalTagCount }}
                        </span>
                    </div>

                    <div v-if="tagGroups.length" class="mt-4 space-y-3">
                        <details
                            v-for="(group, index) in tagGroups"
                            :key="group.model"
                            :open="index === 0"
                            class="overflow-hidden rounded-[22px] border border-[#DCDBDA] bg-[#f7f7f7]"
                        >
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-slate-800 marker:content-none">
                                <div>
                                    <p>{{ group.model }}</p>
                                    <p class="mt-1 text-xs font-normal text-slate-500">{{ group.variable }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                    {{ group.tags.length }}
                                </span>
                            </summary>

                            <div class="border-t border-[#DCDBDA] bg-white px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="tag in group.tags"
                                        :key="`${group.model}-${tag.label}`"
                                        type="button"
                                        class="rounded-full border border-[#DCDBDA] bg-[#f7f7f7] px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-[#DCDBDA]"
                                        @click="insertTag(tag.value)"
                                    >
                                        {{ tag.label }}
                                    </button>
                                </div>
                            </div>
                        </details>
                    </div>

                    <p v-else class="mt-4 text-sm text-slate-500">
                        Aucun tag n'est disponible pour le moment.
                    </p>
                </div>
            </aside>
        </main>

        <main v-else class="grid flex-1 gap-6 lg:grid-cols-[380px_minmax(0,1fr)]">
            <section class="space-y-6 lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-[28px] border border-[#DCDBDA] bg-white p-5 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                    <div class="mb-4">
                        <h2 class="font-['Space_Grotesk'] text-xl font-bold text-slate-950">Templates existants</h2>
                        <p class="mt-1 text-sm text-slate-600">Chaque cle de template porte une regle de diffusion associee.</p>
                    </div>

                    <div class="mb-4">
                        <label for="template-search" class="sr-only">Rechercher un template</label>
                        <input
                            id="template-search"
                            v-model="search"
                            type="search"
                            placeholder="Rechercher un template, une cle ou un event key..."
                            class="w-full rounded-2xl border border-[#DCDBDA] bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                        >
                    </div>

                    <div class="space-y-3">
                        <button
                            v-for="template in filteredTemplates"
                            :key="template.id"
                            type="button"
                            :class="activeTemplate?.id === template.id
                                ? 'border-[#DCDBDA] bg-[#DCDBDA] text-slate-900 shadow-[0_12px_32px_rgba(0,0,0,0.05)]'
                                : 'border-[#DCDBDA] bg-white text-slate-900 hover:bg-[#f7f7f7]'"
                            class="block w-full rounded-3xl border p-4 text-left transition"
                            @click="selectTemplate(template)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold">{{ template.display_name }}</h3>
                                    <p class="mt-1 text-xs" :class="activeTemplate?.id === template.id ? 'text-slate-700' : 'text-slate-500'">
                                        {{ template.key }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em]"
                                    :class="template.rule.active ? 'bg-white text-slate-700' : 'bg-[#f7f7f7] text-slate-500'"
                                >
                                    {{ template.rule.active ? 'active' : 'inactive' }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6" :class="activeTemplate?.id === template.id ? 'text-slate-700' : 'text-slate-600'">
                                {{ template.excerpt || 'Template vide.' }}
                            </p>
                        </button>

                        <div v-if="!isLoading && filteredTemplates.length === 0" class="rounded-3xl border border-dashed border-[#DCDBDA] bg-white px-4 py-8 text-center text-sm text-slate-500">
                            {{ search ? 'Aucun template ne correspond a votre recherche.' : 'Aucun template disponible pour le moment.' }}
                        </div>

                        <div v-if="isLoading" class="rounded-3xl border border-dashed border-[#DCDBDA] bg-white px-4 py-8 text-center text-sm text-slate-500">
                            Chargement des templates...
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[32px] border border-[#DCDBDA] bg-white p-6 text-slate-900 shadow-[0_18px_48px_rgba(0,0,0,0.05)]">
                <template v-if="activeTemplate">
                    <div class="mb-6 flex flex-col gap-4 border-b border-[#DCDBDA] pb-6 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-600">Detail du template</p>
                            <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold">{{ activeTemplate.display_name }}</h2>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                                <span class="rounded-full bg-[#DCDBDA] px-3 py-1 font-semibold text-slate-700">{{ activeTemplate.key }}</span>
                                <span class="rounded-full bg-[#f7f7f7] px-3 py-1">{{ activeTemplate.rule.event_key }}</span>
                            </div>
                        </div>

                        <dl class="grid gap-3 text-sm text-slate-600">
                            <div>
                                <dt class="text-xs uppercase tracking-[0.2em] text-slate-400">Mis a jour</dt>
                                <dd>{{ formatDate(activeTemplate.updated_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-[0.2em] text-slate-400">Taille</dt>
                                <dd>{{ formatSize(activeTemplate.size) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mb-6 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-[24px] border border-[#DCDBDA] bg-[#fafafa] p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Regle</p>
                            <div class="mt-3 space-y-2 text-sm text-slate-700">
                                <p><span class="font-semibold">Canaux :</span> {{ activeTemplate.rule.channels.join(', ') || 'Aucun' }}</p>
                                <p><span class="font-semibold">Fallback :</span> {{ activeTemplate.rule.fallback.join(', ') || 'Aucun' }}</p>
                                <p><span class="font-semibold">Priorite :</span> {{ activeTemplate.rule.priority }}</p>
                                <p><span class="font-semibold">Delai :</span> {{ activeTemplate.rule.delay }} min</p>
                                <p><span class="font-semibold">Etat :</span> {{ activeTemplate.rule.active ? 'Active' : 'Inactive' }}</p>
                            </div>
                        </div>

                        <div class="rounded-[24px] border border-[#DCDBDA] bg-[#fafafa] p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Actions</p>
                            <div class="mt-3 flex flex-col gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-[22px] bg-[#DCDBDA] px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-[#d2d1d0]"
                                    @click="copyContent"
                                >
                                    {{ copied ? 'Contenu copie' : 'Copier le contenu' }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-[22px] border border-[#DCDBDA] bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[#f7f7f7]"
                                    @click="toggleTemplateStatus"
                                >
                                    {{ activeTemplate.rule.active ? 'Desactiver la regle' : 'Activer la regle' }}
                                </button>
                                <a
                                    :href="replaceRouteParam(routes.templates.editPagePattern, '__TEMPLATE__', activeTemplate.id)"
                                    class="inline-flex items-center justify-center rounded-[22px] border border-[#DCDBDA] bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[#f7f7f7]"
                                >
                                    Modifier
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[28px] border border-[#DCDBDA] bg-white p-4">
                        <pre class="overflow-x-auto whitespace-pre-wrap break-words font-mono text-sm leading-7 text-slate-700">{{ activeTemplate.content }}</pre>
                    </div>
                </template>

                <div v-else class="flex h-full items-center justify-center rounded-[28px] border border-dashed border-[#DCDBDA] bg-white p-8 text-center text-slate-500">
                    Creez un premier template pour afficher son detail ici.
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';

const ui = window.__ACL_COMMUNICATIONS_UI__ ?? {};
const routes = ui.routes ?? {
    templates: {
        page: '/communications/templates',
        createPage: '/communications/templates/create',
        editPagePattern: '/communications/templates/__TEMPLATE__/edit',
        index: '/communications/api/templates',
        showPattern: '/communications/api/templates/__TEMPLATE__',
        store: '/communications/api/templates',
        updatePattern: '/communications/api/templates/__TEMPLATE__',
        updateRulePattern: '/communications/api/templates/__TEMPLATE__/rule',
    },
    notifications: {
        page: '/communications/notifications',
    },
};
const channelOptions = [
    { label: 'Mail', value: 'mail' },
];

const templates = ref([]);
const tagGroups = ref([]);
const eventKeyOptions = ref([]);
const activeTemplate = ref(null);
const search = ref('');
const isLoading = ref(true);
const isLoadingEditor = ref(false);
const isSubmitting = ref(false);
const flashMessage = ref('');
const globalError = ref('');
const copied = ref(false);
const errors = ref({});
const nameInput = ref(null);
const editorRef = ref(null);
const templateExampleName = '{{ $name }}';
const templateExampleDate = '{{ $due_date }}';
const currentUrl = new URL(window.location.href);
const viewMode = ui.mode ?? 'list';
const editingTemplateId = ui.editingTemplateId ? Number.parseInt(String(ui.editingTemplateId), 10) : null;
const editorActions = [
    { label: 'H1', command: 'formatBlock', value: 'h1' },
    { label: 'H2', command: 'formatBlock', value: 'h2' },
    { label: 'P', command: 'formatBlock', value: 'p' },
    { label: 'Gras', command: 'bold' },
    { label: 'Italique', command: 'italic' },
    { label: 'Liste', command: 'insertUnorderedList' },
    { label: 'Lien', command: 'createLink', prompt: "Entrez l'URL du lien :" },
];

const defaultForm = () => ({
    name: '',
    content: '',
    rule: {
        event_key: '',
        channels: ['mail'],
        priority: 100,
        fallback: [],
        delay: 0,
        active: true,
    },
});

const form = ref(defaultForm());

const isEditorView = computed(() => viewMode === 'create' || viewMode === 'edit');

const pageTitle = computed(() => {
    if (viewMode === 'create') {
        return 'Creer un template';
    }

    if (viewMode === 'edit') {
        return 'Modifier un template';
    }

    return "Page d'acces templates";
});

const pageDescription = computed(() => {
    if (viewMode === 'create') {
        return "Creez un template et sa regle associee sur un ecran dedie.";
    }

    if (viewMode === 'edit') {
        return "Le contenu HTML et la regle de diffusion sont modifiables depuis la meme page Vue.";
    }

    return "Toute l'interface est geree en Vue 3 pour lister les templates, afficher leur detail et piloter leurs regles.";
});

const submitLabel = computed(() => {
    if (isSubmitting.value) {
        return viewMode === 'edit' ? 'Enregistrement...' : 'Creation...';
    }

    return viewMode === 'edit' ? 'Enregistrer le template et la regle' : 'Creer le template';
});

const filteredTemplates = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return templates.value;
    }

    return templates.value.filter((template) => {
        return [
            template.display_name,
            template.key,
            template.rule.event_key,
            template.excerpt,
        ]
            .join(' ')
            .toLowerCase()
            .includes(query);
    });
});

const totalTagCount = computed(() => tagGroups.value.flatMap((group) => group.tags).length);
const generatedTemplateKey = computed(() => {
    const normalized = form.value.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return normalized || 'template';
});
const selectedEventKeyDescription = computed(() => {
    const selected = eventKeyOptions.value.find((eventKey) => eventKey.key === form.value.rule.event_key);

    return selected?.description ?? '';
});

function buildPayload() {
    return {
        name: form.value.name.trim(),
        content: form.value.content,
        event_key: form.value.rule.event_key.trim(),
        channels: [...form.value.rule.channels],
        priority: form.value.rule.priority,
        fallback: [...form.value.rule.fallback],
        delay: form.value.rule.delay,
        active: form.value.rule.active,
    };
}

function hydrateForm(template) {
    form.value = {
        name: template.name,
        content: template.content.trim(),
        rule: {
            event_key: template.rule.event_key ?? '',
            channels: [...(template.rule.channels ?? [])],
            priority: template.rule.priority ?? 100,
            fallback: [...(template.rule.fallback ?? [])],
            delay: template.rule.delay ?? 0,
            active: Boolean(template.rule.active),
        },
    };
}

async function fetchTemplates() {
    isLoading.value = true;
    globalError.value = '';

    try {
        const { data } = await window.axios.get(routes.templates.index);
        templates.value = data.templates ?? [];
        tagGroups.value = data.tags ?? [];
        eventKeyOptions.value = data.event_keys ?? [];

        if (isEditorView.value) {
            return;
        }

        const selectedId = Number.parseInt(new URLSearchParams(window.location.search).get('template') ?? '', 10);
        activeTemplate.value = templates.value.find((template) => template.id === selectedId) ?? templates.value[0] ?? null;
        syncListUrl();
        setFlashFromUrl();
    } catch (error) {
        globalError.value = 'Impossible de charger les templates pour le moment.';
    } finally {
        isLoading.value = false;
    }
}

async function fetchTemplateForEdit() {
    if (viewMode !== 'edit' || !editingTemplateId) {
        return;
    }

    isLoadingEditor.value = true;
    globalError.value = '';

    try {
        const { data } = await window.axios.get(replaceRouteParam(routes.templates.showPattern, '__TEMPLATE__', editingTemplateId));
        tagGroups.value = data.tags ?? tagGroups.value;
        eventKeyOptions.value = data.event_keys ?? eventKeyOptions.value;
        hydrateForm(data.template);
        await nextTick();
        setEditorContent(form.value.content);
    } catch (error) {
        globalError.value = 'Impossible de charger ce template pour la modification.';
    } finally {
        isLoadingEditor.value = false;
    }
}

function setFlashFromUrl() {
    const params = new URLSearchParams(window.location.search);

    if (params.get('created') === '1') {
        flashMessage.value = 'Template et regle crees avec succes.';
        params.delete('created');
    }

    if (params.get('updated') === '1') {
        flashMessage.value = 'Template mis a jour avec succes.';
        params.delete('updated');
    }

    if (params.get('rule_updated') === '1') {
        flashMessage.value = 'Regle mise a jour avec succes.';
        params.delete('rule_updated');
    }

    const nextUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`;
    window.history.replaceState({}, '', nextUrl);
}

function selectTemplate(template) {
    activeTemplate.value = template;
    copied.value = false;
    syncListUrl();
}

function syncListUrl() {
    if (viewMode !== 'list') {
        return;
    }

    const nextUrl = new URL(window.location.href);

    if (activeTemplate.value?.id) {
        nextUrl.searchParams.set('template', String(activeTemplate.value.id));
    } else {
        nextUrl.searchParams.delete('template');
    }

    window.history.replaceState({}, '', nextUrl);
}

async function submitEditor() {
    if (viewMode === 'edit') {
        await updateTemplate();
        return;
    }

    await createTemplate();
}

async function createTemplate() {
    isSubmitting.value = true;
    errors.value = {};
    globalError.value = '';

    try {
        const { data } = await window.axios.post(routes.templates.store, buildPayload());
        const redirectUrl = new URL(routes.templates.page, window.location.origin);
        redirectUrl.searchParams.set('template', String(data.template.id));
        redirectUrl.searchParams.set('created', '1');
        window.location.assign(redirectUrl.toString());
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            return;
        }

        globalError.value = 'La creation du template a echoue.';
    } finally {
        isSubmitting.value = false;
    }
}

async function updateTemplate() {
    if (!editingTemplateId) {
        return;
    }

    isSubmitting.value = true;
    errors.value = {};
    globalError.value = '';

    try {
        const { data } = await window.axios.put(
            replaceRouteParam(routes.templates.updatePattern, '__TEMPLATE__', editingTemplateId),
            buildPayload(),
        );
        const redirectUrl = new URL(routes.templates.page, window.location.origin);
        redirectUrl.searchParams.set('template', String(data.template.id));
        redirectUrl.searchParams.set('updated', '1');
        window.location.assign(redirectUrl.toString());
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            return;
        }

        globalError.value = 'La mise a jour du template a echoue.';
    } finally {
        isSubmitting.value = false;
    }
}

async function updateRuleOnly() {
    if (!editingTemplateId) {
        return;
    }

    isSubmitting.value = true;
    errors.value = {};
    globalError.value = '';

    try {
        const { data } = await window.axios.patch(replaceRouteParam(routes.templates.updateRulePattern, '__TEMPLATE__', editingTemplateId), {
            event_key: form.value.rule.event_key.trim(),
            channels: [...form.value.rule.channels],
            priority: form.value.rule.priority,
            fallback: [...form.value.rule.fallback],
            delay: form.value.rule.delay,
            active: form.value.rule.active,
        });
        const redirectUrl = new URL(routes.templates.page, window.location.origin);
        redirectUrl.searchParams.set('template', String(data.template.id));
        redirectUrl.searchParams.set('rule_updated', '1');
        window.location.assign(redirectUrl.toString());
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            return;
        }

        globalError.value = "La mise a jour de la regle a echoue.";
    } finally {
        isSubmitting.value = false;
    }
}

async function toggleTemplateStatus() {
    if (!activeTemplate.value) {
        return;
    }

    globalError.value = '';

    try {
        const { data } = await window.axios.patch(replaceRouteParam(routes.templates.updateRulePattern, '__TEMPLATE__', activeTemplate.value.id), {
            event_key: activeTemplate.value.rule.event_key,
            channels: [...activeTemplate.value.rule.channels],
            priority: activeTemplate.value.rule.priority,
            fallback: [...activeTemplate.value.rule.fallback],
            delay: activeTemplate.value.rule.delay,
            active: !activeTemplate.value.rule.active,
        });

        activeTemplate.value = data.template;
        templates.value = templates.value.map((template) => template.id === data.template.id ? data.template : template);
        flashMessage.value = data.template.rule.active ? 'Regle activee.' : 'Regle desactivee.';
    } catch (error) {
        globalError.value = "Impossible de modifier l'etat de la regle.";
    }
}

function syncEditorContent() {
    form.value.content = serializeEditorHtml(editorRef.value?.innerHTML ?? '');
}

function normalizeEditorHtml(html) {
    return html
        .replace(/&nbsp;/g, ' ')
        .replace(/<div><br><\/div>/g, '<p></p>')
        .trim();
}

function decodeHtmlEntities(value) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = value;

    return textarea.value;
}

function escapeHtmlAttribute(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

function escapeHtmlText(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

function serializeEditorHtml(html) {
    const serialized = html.replaceAll(/<span class="template-tag"[^>]*data-tag="([^"]+)"[^>]*>[^<]*<\/span>/g, (_, tagValue) => {
        return decodeHtmlEntities(tagValue);
    });

    return normalizeEditorHtml(decodeHtmlEntities(serialized));
}

function decorateTags(html) {
    return normalizeEditorHtml(html).replaceAll(/{{\s*[^}]+\s*}}/g, (match) => {
        const label = match.replace(/[{}]/g, '').trim();
        return `<span class="template-tag" contenteditable="false" data-tag="${escapeHtmlAttribute(match)}">${escapeHtmlText(label)}</span>`;
    });
}

function setEditorContent(content) {
    if (editorRef.value) {
        editorRef.value.innerHTML = decorateTags(content);
    }
}

function focusEditor() {
    editorRef.value?.focus();
}

function runEditorAction(action) {
    focusEditor();

    if (action.prompt) {
        const value = window.prompt(action.prompt, 'https://');

        if (!value) {
            return;
        }

        document.execCommand(action.command, false, value);
        syncEditorContent();
        return;
    }

    document.execCommand(action.command, false, action.value ?? null);
    syncEditorContent();
}

function insertTag(tagValue) {
    focusEditor();
    document.execCommand(
        'insertHTML',
        false,
        `<span class="template-tag" contenteditable="false" data-tag="${escapeHtmlAttribute(tagValue)}">${escapeHtmlText(tagValue.replace(/[{}]/g, '').trim())}</span>&nbsp;`
    );
    syncEditorContent();
}

async function copyContent() {
    if (!activeTemplate.value || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(activeTemplate.value.content.trim());
    copied.value = true;

    window.setTimeout(() => {
        copied.value = false;
    }, 1800);
}

function formatDate(value) {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatSize(size) {
    return `${new Intl.NumberFormat('fr-FR').format(size)} octets`;
}

function replaceRouteParam(pattern, placeholder, value) {
    return pattern.replace(placeholder, String(value));
}

onMounted(async () => {
    await fetchTemplates();

    if (viewMode === 'create') {
        await nextTick();
        nameInput.value?.focus();
        form.value = {
            ...defaultForm(),
            content: `<h1>Bonjour ${templateExampleName}</h1><p>Votre facture arrive a echeance le ${templateExampleDate}.</p>`,
        };
        setEditorContent(form.value.content);
        return;
    }

    if (viewMode === 'edit') {
        await nextTick();
        nameInput.value?.focus();
        await fetchTemplateForEdit();
        return;
    }

    setFlashFromUrl();
});
</script>

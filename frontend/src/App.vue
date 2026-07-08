<script setup>
import { ref, onMounted } from 'vue'
import { api } from './api.js'

const posts = ref([])
const error = ref('')
const loading = ref(false)

// Formulaire (création ou édition selon editingId)
const form = ref({ author: '', content: '' })
const editingId = ref(null)

function resetForm() {
  form.value = { author: '', content: '' }
  editingId.value = null
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    posts.value = await api.list()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function submit() {
  error.value = ''
  if (!form.value.author.trim() || !form.value.content.trim()) {
    error.value = 'Auteur et message sont obligatoires.'
    return
  }
  try {
    if (editingId.value) {
      await api.update(editingId.value, form.value)
    } else {
      await api.create(form.value)
    }
    resetForm()
    await load()
  } catch (e) {
    error.value = e.message
  }
}

function startEdit(post) {
  editingId.value = post.id
  form.value = { author: post.author, content: post.content }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function remove(post) {
  if (!confirm(`Supprimer le message de ${post.author} ?`)) return
  try {
    await api.remove(post.id)
    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function like(post) {
  try {
    await api.update(post.id, { likes: post.likes + 1 })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

function formatDate(iso) {
  return new Date(iso).toLocaleString('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

onMounted(load)
</script>

<template>
  <h1>💬 Mini Réseau Social</h1>
  <p class="subtitle">Projet Fil Rouge — Vue.js + Symfony + PostgreSQL sur Kubernetes</p>

  <p v-if="error" class="error">⚠️ {{ error }}</p>

  <!-- Formulaire CRUD : create / update -->
  <form class="card" @submit.prevent="submit">
    <label for="author">Auteur</label>
    <input id="author" v-model="form.author" placeholder="Votre nom" maxlength="100" />

    <label for="content">Message</label>
    <textarea id="content" v-model="form.content" placeholder="Quoi de neuf ?"></textarea>

    <div class="post-actions">
      <button type="submit" class="btn-primary">
        {{ editingId ? 'Mettre à jour' : 'Publier' }}
      </button>
      <button v-if="editingId" type="button" class="btn-ghost" @click="resetForm">
        Annuler
      </button>
    </div>
  </form>

  <!-- Liste des posts -->
  <p v-if="loading" class="empty">Chargement…</p>
  <p v-else-if="posts.length === 0" class="empty">
    Aucun message pour l'instant. Soyez le premier à publier !
  </p>

  <article v-for="post in posts" :key="post.id" class="card">
    <div class="post-head">
      <span class="post-author">{{ post.author }}</span>
      <span class="post-date">{{ formatDate(post.createdAt) }}</span>
    </div>
    <p class="post-content">{{ post.content }}</p>
    <div class="post-actions">
      <button class="likes" @click="like(post)">❤️ {{ post.likes }}</button>
      <span class="spacer"></span>
      <button class="btn-ghost" @click="startEdit(post)">Modifier</button>
      <button class="btn-danger" @click="remove(post)">Supprimer</button>
    </div>
  </article>
</template>

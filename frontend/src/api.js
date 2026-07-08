// Toutes les requêtes sont relatives : en production l'Ingress (ou le proxy
// nginx du frontend) route /api vers l'API Symfony.
const BASE = '/api'

async function handle(res) {
  if (res.status === 204) return null
  const body = await res.json().catch(() => null)
  if (!res.ok) {
    throw new Error(body?.error || `Erreur ${res.status}`)
  }
  return body
}

export const api = {
  list() {
    return fetch(`${BASE}/posts`).then(handle)
  },
  create(post) {
    return fetch(`${BASE}/posts`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(post),
    }).then(handle)
  },
  update(id, post) {
    return fetch(`${BASE}/posts/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(post),
    }).then(handle)
  },
  remove(id) {
    return fetch(`${BASE}/posts/${id}`, { method: 'DELETE' }).then(handle)
  },
}

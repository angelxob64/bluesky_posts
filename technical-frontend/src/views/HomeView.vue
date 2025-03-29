<template>
  <div class="container">
    <h1 class="title">🔍 Busqueda de Posts Bluesky</h1>

    <!-- Filter Inputs -->
    <div class="search-box">
      <input v-model="text" type="text" placeholder="Texto..." />
      <input v-model="tags" type="text" placeholder="Tags..." />
      <input v-model="mentions" type="text" placeholder="Menciones..." />
      <button @click="searchFeed">Buscar</button>
    </div>

    <!-- Sort Buttons -->
    <div class="sort-buttons">
      <button
        v-for="option in sortOptions"
        :key="option.value"
        :class="{ active: sort === option.value }"
        @click="setSort(option.value)"
      >
        {{ option.label }}
      </button>
    </div>

    <!-- Results -->
    <div v-if="loading && feed.length === 0" class="loading">Cargando posts...</div>
    <div v-else-if="error" class="error">Error: {{ error }}</div>

    <div v-else class="feed">
      <div v-for="(item, index) in feed" :key="index" class="card">
        <img :src="item.avatar" alt="avatar" class="avatar" />
        <div class="content">
          <div class="header">
            <div>
              <strong>{{ item.displayName }}</strong>
              <small>@{{ item.handle }}</small>
            </div>
            <span class="date">{{ formatDate(item.date) }}</span>
          </div>
          <p class="text">{{ item.text }}</p>
          <div class="stats">
            <span>💬 {{ item.replyCount }}</span>
            <span>🔁 {{ item.repostCount }}</span>
            <span>❤️ {{ item.likeCount }}</span>
            <span>📢 {{ item.quoteCount }}</span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <button :disabled="page === 0" @click="prevPage">Anterior</button>
        <button :disabled="!hasMore" @click="nextPage">Siguiente</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const feed = ref([])
const loading = ref(false)
const error = ref(null)
const page = ref(0)
const hasMore = ref(true)

const text = ref('')
const tags = ref('')
const mentions = ref('')
const sort = ref('createdAt')

const sortOptions = [
  { label: 'Lo más nuevo!', value: 'createdAt' },
  { label: 'Comentarios antiguos', value: 'createdAtAsc' },
  { label: 'Más replies', value: 'replyCount' },
  { label: 'Más reposts', value: 'repostCount' },
  { label: 'Los más gustados!', value: 'likeCount' },
  { label: 'Citados frecuentemente', value: 'quoteCount' },
]

const fetchPosts = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await axios.get(`${import.meta.env.VITE_API_BASE_URL}/api/search`, {
      params: {
        q: text.value,
        tags: tags.value,
        mentions: mentions.value,
        page: page.value,
        perPage: 10,
        sort: sort.value,
      },
    })
    feed.value = res.data.posts
    hasMore.value = res.data.hasMore
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const searchFeed = () => {
  page.value = 0
  fetchPosts()
}

const nextPage = () => {
  if (!hasMore.value) return
  page.value++
  fetchPosts().then(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  })
}

const prevPage = () => {
  if (page.value === 0) return
  page.value--
  fetchPosts().then(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  })
}


const setSort = (value) => {
  sort.value = value
  searchFeed()
}

const formatDate = (iso) => {
  return new Date(iso).toLocaleString()
}
</script>

<style scoped>
body {
  background-color: #f0f2f5;
  font-family: 'Segoe UI', sans-serif;
}

.container {
  max-width: 700px;
  margin: 0 auto;
  padding: 1rem;
}

.title {
  text-align: center;
  font-size: 2rem;
  color: #2c3e50;
  margin-bottom: 1rem;
}

.search-box {
  background: #fff;
  padding: 1rem;
  border-radius: 10px;
  margin-bottom: 1.5rem;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.search-box input {
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 5px;
}

.search-box button {
  background-color: #007bff;
  color: #fff;
  padding: 0.6rem;
  border: none;
  border-radius: 5px;
  font-weight: bold;
  cursor: pointer;
}

.search-box button:hover {
  background-color: #0056b3;
}

.sort-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.sort-buttons button {
  background: #f4f4f4;
  border: 1px solid #ccc;
  padding: 0.4rem 0.8rem;
  border-radius: 5px;
  cursor: pointer;
}

.sort-buttons button.active {
  background-color: #007bff;
  color: white;
  font-weight: bold;
  border-color: #007bff;
}

.loading,
.error {
  text-align: center;
  color: #666;
}

.feed {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.card {
  display: flex;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
  padding: 1rem;
  gap: 1rem;
}

.avatar {
  width: 50px;
  height: 50px;
  border-radius: 9999px;
  object-fit: cover;
  border: 2px solid #ddd;
}

.content {
  flex: 1;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header small {
  display: block;
  color: #888;
  font-size: 0.8rem;
}

.date {
  font-size: 0.75rem;
  color: #aaa;
}

.text {
  margin-top: 0.5rem;
  white-space: pre-line;
}

.stats {
  margin-top: 0.5rem;
  font-size: 0.85rem;
  color: #555;
  display: flex;
  gap: 1rem;
}

.pagination {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin-top: 1.5rem;
}

.pagination button {
  padding: 0.5rem 1rem;
  border-radius: 5px;
  border: 1px solid #ccc;
  background: #fff;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'          // <-- changed

const items = ref([])
const loading = ref(false)

async function fetchWishlist() {
    loading.value = true
    try {
        const types = ['movie', 'book', 'game', 'music']
        const results = await Promise.all(
            types.map(type =>
                api.get(`/collection/${type}`, { params: { status: 'wishlist', per_page: 100 } })
                    .then(res => res.data.data.map(item => ({ ...item, _type: type })))
            )
        )
        items.value = results.flat()
    } catch (err) {
        console.error(err)
    } finally {
        loading.value = false
    }
}

onMounted(fetchWishlist)
</script>

<!-- template stays exactly the same -->
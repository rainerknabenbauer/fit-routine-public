<script>
    import { onMount } from 'svelte';

    let token = null;
    let exercises = [];
    let loading = false;
    let error = null;
    let editingExercise = null;
    let showForm = false;
    let showDeleted = false;

    let formData = {
        name: '',
        description: '',
        image_url: '',
        sets: 0,
        repetitions: 0,
        type: 'Strength'
    };

    let exerciseTypes = ['Strength', 'Yoga', 'Stretching', 'Allround'];

    onMount(() => {
        const urlParams = new URLSearchParams(window.location.search);
        token = urlParams.get('token');
        if (token) {
            verifyTokenAndLoadData();
        }
    });

    async function sendLoginLink() {
        try {
            loading = true;
            await fetch('/api/admin/send-login', {
                method: 'POST'
            });
            loading = false;
            alert('Login link has been sent to your email');
        } catch (err) {
            error = 'Failed to send login link';
            loading = false;
        }
    }

    async function verifyTokenAndLoadData() {
        try {
            const response = await fetch('/api/admin/verify-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ token })
            });

            if (response.ok) {
                await loadExercises();
            } else {
                token = null;
                error = 'Invalid token';
            }
        } catch (err) {
            error = 'Failed to verify token';
        }
    }

    async function loadExercises() {
    try {
        const response = await fetch('/api/admin/exercises', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const data = await response.json();
        exercises = data.exercises;
    } catch (err) {
        error = 'Failed to load exercises';
    }
}

function editExercise(exercise) {
    editingExercise = exercise;
    formData = { ...exercise };
    showForm = true;
    // Scroll to top of the page
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function newExercise() {
    editingExercise = null;
    formData = {
        name: '',
        description: '',
        image_url: "https://routine.nykon.de/images/",
        sets: 0,
        repetitions: 0,
        type: 'Strength'
    };
    showForm = true;
}

async function saveExercise() {
    try {
        const url = editingExercise 
            ? '/api/admin/exercise'
            : '/api/admin/exercises';
        
        const method = editingExercise ? 'PUT' : 'POST';
        const body = editingExercise 
            ? { ...formData, id: editingExercise.id }
            : formData;

        await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(body)
        });

        await loadExercises();
        showForm = false;
    } catch (err) {
        error = 'Failed to save exercise';
    }
}

    async function deleteExercise(id) {
        if (!confirm('Are you sure you want to delete this exercise?')) return;

        try {
            await fetch('/api/admin/exercise', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ id })
            });
            await loadExercises();
        } catch (err) {
            error = 'Failed to delete exercise';
        }
    }

    async function restoreExercise(id) {
        if (!confirm('Are you sure you want to restore this exercise?')) return;

        try {
            await fetch('/api/admin/exercise/restore', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ id })
            });
            await loadExercises();
        } catch (err) {
            error = 'Failed to restore exercise';
        }
    }
</script>

<main class="container">
    {#if !token}
        <div class="login-section">
            <h1>Exercise Admin</h1>
            <button on:click={sendLoginLink} disabled={loading}>
                {loading ? 'Sending...' : 'Send Login Link'}
            </button>
            {#if error}
                <p class="error">{error}</p>
            {/if}
        </div>
    {:else}
        <div class="admin-panel">
            <h1>Exercise Management</h1>
            
            <div class="controls">
                <button on:click={newExercise} class="add-button">Add New Exercise</button>
                <label class="show-deleted">
                    <input 
                        type="checkbox" 
                        bind:checked={showDeleted}
                    > Show deleted exercises
                </label>
            </div>

            {#if showForm}
            <div class="form-container">
                <h2>{editingExercise ? 'Edit Exercise' : 'New Exercise'}</h2>
                <form on:submit|preventDefault={saveExercise}>
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input id="name" bind:value={formData.name} required />
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" bind:value={formData.description} required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="type">Type</label>
                        <select id="type" bind:value={formData.type} required>
                            {#each exerciseTypes as type}
                                <option value={type}>{type}</option>
                            {/each}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="image_url">Image URL</label>
                        <input 
                            id="image_url" 
                            type="url" 
                            bind:value={formData.image_url} 
                            required 
                        />
                    </div>
                    <div class="form-group">
                        <label for="sets">Sets</label>
                        <input id="sets" type="number" bind:value={formData.sets} min="0" required />
                    </div>
                    <div class="form-group">
                        <label for="repetitions">Repetitions</label>
                        <input id="repetitions" type="number" bind:value={formData.repetitions} min="0" required />
                    </div>
                    <div class="button-group">
                        <button type="submit">Save</button>
                        <button type="button" on:click={() => (showForm = false)}>Cancel</button>
                    </div>
                </form>
            </div>
        {/if}

        <div class="exercises-list">
            {#each exercises.filter(ex => showDeleted || !ex.deleted) as exercise}
                <div class="exercise-item" class:deleted={exercise.deleted}>
                    <img src={exercise.image_url} alt={exercise.name}>
                    <div class="exercise-details">
                        <h3>
                            {exercise.name}
                            {#if exercise.deleted}
                                <span class="deleted-badge">Deleted</span>
                            {/if}
                        </h3>
                        <p>{exercise.description}</p>
                        <p>Type: {exercise.type}</p>
                        <p>Sets: {exercise.sets}</p>
                        <p>Repetitions: {exercise.repetitions}</p>
                        {#if exercise.deleted}
                            <p class="deleted-info">Deleted at: {new Date(exercise.deleted_at).toLocaleString()}</p>
                        {/if}
                    </div>
                    <div class="exercise-actions">
                        {#if !exercise.deleted}
                            <button on:click={() => editExercise(exercise)}>Edit</button>
                            <button on:click={() => deleteExercise(exercise.id)} class="delete">
                                Delete
                            </button>
                        {:else}
                            <button on:click={() => restoreExercise(exercise.id)} class="restore">
                                Restore
                            </button>
                        {/if}
                    </div>
                </div>
            {/each}
        </div>
        </div>
    {/if}
</main>

<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .login-section {
        text-align: center;
        margin-top: 100px;
    }

    .form-container {
        background: #f5f5f5;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .button-group {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .exercise-item {
        display: flex;
        gap: 20px;
        padding: 20px;
        border-bottom: 1px solid #ddd;
    }

    .exercise-item img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 4px;
    }

    .exercise-details {
        flex: 1;
    }

    .exercise-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .delete {
        background: #ff4444;
    }

    .error {
        color: #ff4444;
        margin-top: 10px;
    }

    button {
        padding: 8px 16px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        opacity: 0.9;
    }

    button:disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    .add-button {
        margin-bottom: 20px;
    }

    .controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .show-deleted {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .exercise-item.deleted {
        opacity: 0.7;
        background: #f8f8f8;
    }

    .deleted-badge {
        background: #ff4444;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        margin-left: 8px;
    }

    .deleted-info {
        color: #666;
        font-size: 0.9em;
        font-style: italic;
    }

    .restore {
        background: #4a90e2;
    }

    .restore:hover {
        background: #357abd;
    }

    button.restore {
        background: #4a90e2;
    }

    input[type="checkbox"] {
        margin: 0;
    }

    .exercise-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-width: 100px;
    }
    .form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>
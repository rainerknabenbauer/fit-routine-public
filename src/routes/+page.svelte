<script>
    import { onMount } from 'svelte';
    import Cookies from 'js-cookie';

    const EMAIL_COOKIE = 'exercise_user_email';
    const USER_ID_COOKIE = 'exercise_user_id';
    
    let email = Cookies.get(EMAIL_COOKIE) || '';
    let userId = Cookies.get(USER_ID_COOKIE) || null;
    let exercise = null;
    let todayProgress = 0;
    let loading = true;
    let error = null;
    let showEmailInput = !email;
    let showFullscreenImage = false;
    let isClosing = false;

    async function registerUser() {
        if (!email || !email.includes('@')) {
            error = 'Please enter a valid email address';
            return;
        }

        try {
            const response = await fetch('/api/user/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();
            if (data.userId) {
                userId = data.userId;
                Cookies.set(EMAIL_COOKIE, email, { expires: 365 });
                Cookies.set(USER_ID_COOKIE, userId, { expires: 365 });
                showEmailInput = false;
                await initializeApp();
            } else {
                error = 'Failed to register user';
            }
        } catch (err) {
            error = 'Failed to register user';
        }
    }

    async function verifyUser() {
        try {
            const response = await fetch('/api/user/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();
            if (data.userId) {
                userId = data.userId;
                return true;
            }
        } catch (err) {
            return false;
        }
        return false;
    }

  async function fetchRandomExercise() {
        try {
            loading = true;
            const response = await fetch('/api/exercise/random');
            exercise = await response.json();
            loading = false;
        } catch (err) {
            error = 'Failed to load exercise';
            loading = false;
        }
    }

    async function fetchTodayProgress() {
        try {
            const response = await fetch(`/api/progress/today?userId=${userId}`);
            const data = await response.json();
            todayProgress = data.completed;
        } catch (err) {
            error = 'Failed to load progress';
        }
    }

    async function markCompleted() {
        try {
            await fetch('/api/exercise/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    exerciseId: exercise.id,
                    userId: userId
                }),
            });
            await fetchTodayProgress();
            if (todayProgress < 6) {
                await fetchRandomExercise();
            }
        } catch (err) {
            error = 'Failed to mark exercise as completed';
        }
    }

    async function initializeApp() {
        await fetchRandomExercise();
        await fetchTodayProgress();
    }

    onMount(async () => {
        if (email && !userId) {
            const verified = await verifyUser();
            if (verified) {
                Cookies.set(USER_ID_COOKIE, userId, { expires: 365 });
                showEmailInput = false;
                await initializeApp();
            } else {
                showEmailInput = true;
            }
        } else if (!showEmailInput) {
            await initializeApp();
        }
    });

    function closeImage() {
        isClosing = true;
        setTimeout(() => {
            showFullscreenImage = false;
            isClosing = false;
        }, 300); // Match the animation duration
    }
</script>

<main class="container">
  {#if showEmailInput}
      <div class="email-form">
          <h1>Welcome to Daily Exercise Routine</h1>
          <p>Please enter your email to get started:</p>
          <input 
              type="email" 
              bind:value={email} 
              placeholder="your@email.com"
              on:keydown={e => e.key === 'Enter' && registerUser()}
          />
          <button on:click={registerUser}>Start Exercising</button>
          {#if error}
              <p class="error">{error}</p>
          {/if}
      </div>
  {:else if loading}
      <p>Loading exercise...</p>
  {:else if error}
      <p class="error">{error}</p>
  {:else if todayProgress >= 6}
      <div class="success-screen">
          <h1>🎉 Congratulations! 🎉</h1>
          <p>You've completed all 6 exercises for today.</p>
          <p>Come back tomorrow for more exercises!</p>
      </div>
  {:else if exercise}
      <div class="exercise-card">
          <h1>{exercise.name}</h1>
          <button 
          class="image-button"
          on:click={() => showFullscreenImage = true}
          on:keydown={e => e.key === 'Enter' && (showFullscreenImage = true)}
          aria-label="View full size image of {exercise.name}"
      >
          <img 
              src={exercise.image_url} 
              alt={exercise.name} 
          />
      </button>
          <p class="description">{exercise.description}</p>
          <div class="details">
              <p>Sets: {exercise.sets}</p>
              <p>Repetitions: {exercise.repetitions}</p>
          </div>
          <button class="accomplish-button" on:click={markCompleted}>
              Mark as Accomplished
          </button>
          <button class="reroll-button" on:click={fetchRandomExercise}>
            Reroll Exercise
        </button>
      </div>
  {/if}

  {#if !showEmailInput}
      <footer class="progress-footer">
          {#each Array(6) as _, i}
              <div 
                  class="progress-block"
                  class:completed={i < todayProgress}
              >
                  {i + 1}
              </div>
          {/each}
      </footer>
  {/if}
  {#if showFullscreenImage}
  <div 
      class="image-overlay {isClosing ? 'closing' : ''}"
      role="dialog"
      aria-label="Image preview"
  >
      <button 
          class="overlay-button"
          on:click={closeImage}
          on:keydown={e => e.key === 'Escape' && closeImage()}
          aria-label="Close full size image"
      >
          <img 
              src={exercise.image_url} 
              alt={exercise.name}
              class="fullscreen-image"
          />
      </button>
  </div>
{/if}
</main>

<style>
  .container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px 20px 100px 20px;
      min-height: 100vh;
      position: relative;
  }

  .email-form {
      text-align: center;
      max-width: 400px;
      margin: 100px auto;
  }

  .email-form input {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 16px;
  }

  .exercise-card {
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
  }

  .exercise-card img {
      width: 100%;
      max-height: 400px;
      object-fit: cover;
      border-radius: 4px;
  }

  .description {
      margin: 20px 0;
      line-height: 1.6;
  }

  .details {
      display: flex;
      justify-content: space-between;
      margin: 20px 0;
  }

  button {
    width: 100%;
    padding: 12px;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
    margin-bottom: 8px;
}

.accomplish-button {
    background: #4CAF50;
}

.accomplish-button:hover {
    background: #45a049;
}

.reroll-button {
    background: #2196F3;
}

.reroll-button:hover {
    background: #1976D2;
}

  .progress-footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      display: flex;
      height: 60px;
      background: #f5f5f5;
  }

  .progress-block {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f0f0f0;
      border-right: 1px solid #ddd;
      font-weight: bold;
      color: #666;
      transition: all 0.3s ease;
  }

  .progress-block.completed {
      background: #4CAF50;
      color: white;
  }

  .success-screen {
      text-align: center;
      padding: 40px;
      background: #f8f8f8;
      border-radius: 8px;
      margin-top: 40px;
  }

  .success-screen h1 {
      color: #4CAF50;
      margin-bottom: 20px;
  }

  .error {
      color: #f44336;
      text-align: center;
      margin-top: 10px;
  }

.fullscreen-image {
    max-width: 95vw;
    max-height: 95vh;
    object-fit: contain;
    border-radius: 4px;
    animation: scaleIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes scaleIn {
    from {
        transform: scale(0.95);
    }
    to {
        transform: scale(1);
    }
}
.image-button {
    padding: 0;
    border: none;
    background: none;
    cursor: pointer;
    width: 100%;
    transition: transform 0.2s ease;
}

.image-button:hover {
    transform: scale(1.02);
}

.image-button:focus {
    outline: 2px solid #42a5f5;
    outline-offset: 2px;
}

.image-button img {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-radius: 4px;
}

.image-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    animation: overlayFadeIn 0.3s ease forwards;
}

.overlay-button {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    width: 95vw;
    height: 95vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.overlay-button:focus {
    outline: none;
}

.overlay-button:focus-visible {
    outline: 2px solid #42a5f5;
    outline-offset: 4px;
}

.fullscreen-image {
    max-width: 95vw;
    max-height: 95vh;
    object-fit: contain;
    border-radius: 4px;
    opacity: 0;
    transform: scale(0.95);
    animation: imageFadeIn 0.4s ease 0.1s forwards;
}

/* Opening animations */
@keyframes overlayFadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes imageFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Closing animations */
.image-overlay.closing {
    animation: overlayFadeOut 0.3s ease forwards;
}

.image-overlay.closing .fullscreen-image {
    animation: imageFadeOut 0.2s ease forwards;
}

@keyframes overlayFadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

@keyframes imageFadeOut {
    from {
        opacity: 1;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(0.95);
    }
}
</style>
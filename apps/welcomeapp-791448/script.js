document.addEventListener('DOMContentLoaded', () => {
  const greetingPill = document.getElementById('greetingPill');
  const welcomeMessage = document.getElementById('welcomeMessage');
  const currentTimeDisplay = document.getElementById('currentTime');
  const welcomeBtn = document.getElementById('welcomeBtn');
  const canvas = document.getElementById('ambientCanvas');
  const ctx = canvas.getContext('2d');

  // Time and Greeting Logic
  function updateTimeAndGreeting() {
    const now = new Date();
    const hours = now.getHours();

    // Deterministic period check
    let period = 'Welcome';
    let subtext = 'We are thrilled to have you here. Step inside and make yourself at home.';

    if (hours >= 5 && hours < 12) {
      period = 'Good Morning & Welcome';
      subtext = 'Wishing you an inspiring, productive, and wonderfully bright day ahead.';
    } else if (hours >= 12 && hours < 17) {
      period = 'Good Afternoon & Welcome';
      subtext = 'Hope your day is going great. Take a breath and enjoy your stay.';
    } else if (hours >= 17 && hours < 22) {
      period = 'Good Evening & Welcome';
      subtext = 'Relax and unwind. We are delighted to welcome you this evening.';
    } else {
      period = 'Good Night & Welcome';
      subtext = 'Late night peace and quiet. Welcome to your calm space.';
    }

    if (greetingPill) greetingPill.textContent = period;
    if (welcomeMessage && !welcomeBtn.dataset.interacted) {
      welcomeMessage.textContent = subtext;
    }

    if (currentTimeDisplay) {
      currentTimeDisplay.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
  }

  updateTimeAndGreeting();
  setInterval(updateTimeAndGreeting, 1000);

  // Button Interaction
  const welcomingPhrases = [
    'You are warmly welcomed! ✨',
    'Glad to have you with us today! 🌟',
    'Welcome! Great things await you. 🚀',
    'A warm and heartfelt welcome! 💖'
  ];

  let phraseIndex = 0;
  if (welcomeBtn) {
    welcomeBtn.addEventListener('click', () => {
      welcomeBtn.dataset.interacted = 'true';
      const phrase = welcomingPhrases[phraseIndex % welcomingPhrases.length];
      phraseIndex++;

      welcomeMessage.style.opacity = '0';
      welcomeMessage.style.transform = 'translateY(4px)';
      welcomeMessage.style.transition = 'all 0.25s ease';

      setTimeout(() => {
        welcomeMessage.textContent = phrase;
        welcomeMessage.style.opacity = '1';
        welcomeMessage.style.transform = 'translateY(0)';
      }, 250);

      // Trigger burst effect
      burstParticles();
    });
  }

  // Canvas Ambient Particle System
  let width = (canvas.width = window.innerWidth);
  let height = (canvas.height = window.innerHeight);

  window.addEventListener('resize', () => {
    width = canvas.width = window.innerWidth;
    height = canvas.height = window.innerHeight;
  });

  const particles = [];
  const PARTICLE_COUNT = 32;

  for (let i = 0; i < PARTICLE_COUNT; i++) {
    particles.push({
      x: Math.random() * width,
      y: Math.random() * height,
      size: Math.random() * 2 + 1,
      speedX: (Math.random() - 0.5) * 0.4,
      speedY: (Math.random() - 0.5) * 0.4,
      alpha: Math.random() * 0.5 + 0.2
    });
  }

  function burstParticles() {
    const centerX = width / 2;
    const centerY = height / 2;

    for (let i = 0; i < 20; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = Math.random() * 3 + 1.5;
      particles.push({
        x: centerX,
        y: centerY,
        size: Math.random() * 3 + 1.5,
        speedX: Math.cos(angle) * speed,
        speedY: Math.sin(angle) * speed,
        alpha: 1,
        life: 60
      });
    }
  }

  function render() {
    ctx.clearRect(0, 0, width, height);

    for (let i = particles.length - 1; i >= 0; i--) {
      const p = particles[i];
      p.x += p.speedX;
      p.y += p.speedY;

      if (p.life !== undefined) {
        p.life--;
        p.alpha = p.life / 60;
        if (p.life <= 0) {
          particles.splice(i, 1);
          continue;
        }
      } else {
        if (p.x < 0) p.x = width;
        if (p.x > width) p.x = 0;
        if (p.y < 0) p.y = height;
        if (p.y > height) p.y = 0;
      }

      ctx.beginPath();
      ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(165, 180, 252, ${p.alpha})`;
      ctx.fill();
    }

    requestAnimationFrame(render);
  }

  render();
});
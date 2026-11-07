const menuToggle = document.querySelector('.menu-toggle');
const siteNav = document.querySelector('.site-nav');
const form = document.querySelector('#booking-form');
const formMessage = document.querySelector('#form-message');
const dateInput = form.querySelector('[name="fecha"]');
const timeInput = form.querySelector('[name="hora"]');
const routeInput = form.querySelector('[name="recorrido"]');
const scheduleHelp = document.querySelector('#schedule-help');
const routeDurations = { cienagas: 3, magdalena: 5, completa: 8 };
dateInput.min = new Date().toISOString().slice(0, 10);

function updateScheduleLimit() {
  const duration = routeDurations[routeInput.value] || 3;
  const latestHour = 20 - duration;
  timeInput.max = `${String(latestHour).padStart(2, '0')}:00`;
  scheduleHelp.textContent = `Para esta ruta puedes salir entre las 06:00 y las ${timeInput.max}.`;
  if (timeInput.value > timeInput.max) {
    timeInput.value = '';
  }
}

routeInput.addEventListener('change', updateScheduleLimit);
updateScheduleLimit();

menuToggle.addEventListener('click', () => {
  const isOpen = siteNav.classList.toggle('open');
  menuToggle.setAttribute('aria-expanded', String(isOpen));
});

document.querySelectorAll('.site-nav a').forEach((link) => {
  link.addEventListener('click', () => {
    siteNav.classList.remove('open');
    menuToggle.setAttribute('aria-expanded', 'false');
  });
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  const data = Object.fromEntries(new FormData(form).entries());
  data.chaleco = form.elements.chaleco.checked;
  data.almuerzo = form.elements.almuerzo.checked;
  data.fotos = form.elements.fotos.checked;
  formMessage.textContent = 'Guardando tu reserva...';

  try {
    const response = await fetch('api/reservas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
      throw new Error(result.mensaje || 'No fue posible guardar la reserva.');
    }
    formMessage.textContent = result.mensaje;
    form.reset();
    dateInput.min = new Date().toISOString().slice(0, 10);
  } catch (error) {
    formMessage.textContent = error.message;
  }
});

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));

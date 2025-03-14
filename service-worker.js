self.addEventListener('push', (event) => {
  let data = JSON.parse(atob(event.data.text()));
  self.registration.showNotification(data.title, { body: data.description });
});

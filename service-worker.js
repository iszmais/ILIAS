self.addEventListener('install', () => {
  fetch(self.location.origin + '/wpn-config.php').then((res) => res.json()).then((config) => {
    self.registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: config.public,
    }).then((sub) => {
      let data = new FormData();
      data.append('subscription', btoa(JSON.stringify(sub.toJSON())));
      fetch(self.location.origin + '/wpn-config.php', { method: 'POST', body: data });
    });
  });
});

self.addEventListener('message', () => {
  self.registration.pushManager.getSubscription()
    .then((sub) => sub.unsubscribe()
      .then(() => {
        let data = new FormData();
        data.append('auth', btoa(sub.toJSON().keys.auth));
        fetch(self.location.origin + '/wpn-config.php', { method: 'POST', body: data }).then(() => {
          self.registration.unregister();
        });
      })
    );
});

self.addEventListener('push', (event) => {
  let data = JSON.parse(atob(event.data.text()));
  self.registration.showNotification(data.title, { body: data.description });
});

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ******************************************************************** */

const warning = document.querySelector('.ilAdminRow .alert-warning');
let add;
let remove;

const changeSubcriptionControl = () => {
  navigator.serviceWorker.ready.then((reg) => {
    reg.pushManager.getSubscription().then((sub) => {
      switch (Notification.permission) {
        case 'granted':
          warning.style.display = 'none';
          if (sub === null) {
            add.style.display = 'block';
            remove.style.display = 'none';
          } else {
            add.style.display = 'none';
            remove.style.display = 'block';
          }
          break;
        case 'denied':
          warning.style.display = 'block';
          add.style.display = 'none';
          remove.style.display = 'none';
          break;
        case 'default':
        default:
          warning.style.display = 'block';
          add.style.display = 'block';
          remove.style.display = 'none';
          break;
      }
    });
  });
};

navigator.permissions.query({ name: 'notifications' }).then((status) => {
  status.addEventListener('change', () => changeSubcriptionControl());
});

il.Notifications = il.Notifications || {};
il.Notifications.checkSubscription = (url) => {
  navigator.serviceWorker.ready.then((reg) => {
    reg.pushManager.getSubscription().then((sub) => {
      if (sub !== null) {
        const data = new FormData();
        data.append('auth', sub.toJSON().keys.auth);
        fetch(url, { method: 'POST', body: data })
          .then((response) => response.text())
          .then((response) => {
            if (response !== '') {
              warning.innerHTML = response;
              warning.style.display = 'block';
              document.querySelector('#ilContentContainer').innerHTML = '';
              return false;
            }
          });
      }
    });
  });
  changeSubcriptionControl();
};

il.Notifications.initSub = (element, key, target) => {
  add = element;
  element.addEventListener('click', () => {
    Notification.requestPermission()
      .then((permission) => {
        if (permission === 'granted') {
          navigator.serviceWorker.ready.then((reg) => {
            reg.pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: key,
            })
              .then((newSub) => {
                const data = new FormData();
                data.append('subscription', JSON.stringify(newSub.toJSON()));
                fetch(target, { method: 'POST', body: data });
                changeSubcriptionControl();
              });
          });
        }
      });
  });
};

il.Notifications.initUnsub = (element, target) => {
  remove = element;
  element.addEventListener('click', () => {
    navigator.serviceWorker.ready.then((reg) => {
      reg.pushManager.getSubscription().then((sub) => {
        sub.unsubscribe()
          .then(() => {
            const data = new FormData();
            data.append('auth', sub.toJSON().keys.auth);
            fetch(target, { method: 'POST', body: data });
            changeSubcriptionControl();
          });
      });
    });
  });
};

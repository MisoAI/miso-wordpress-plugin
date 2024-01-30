(($) => {

// update recent tasks from heartbeat response
$(document).on('heartbeat-tick', (event, { miso_recent_tasks } = {}) => {
  for (const task of miso_recent_tasks) {
    const { uploaded = 0, total = 0, deleted = 0 } = task.data || {};
    const $tr = $('#recent-tasks tr[data-task-id="' + task.id + '"]');
    if ($tr.length === 0) {
      $('#recent-tasks tbody').prepend(`<tr data-task-id="${task.id}"><td class="column-columnname">${task.status}</td><td class="column-columnname">${uploaded} / ${total}</td><td class="column-columnname">${deleted}</td><td class="column-columnname">${task.created_at}</td><td class="column-columnname">${task.modified_at}</td></tr>`);
    } else {
      $tr.find('td:nth-child(1)').text(task.status);
      $tr.find('td:nth-child(2)').text(`${uploaded} / ${total}`);
      $tr.find('td:nth-child(3)').text(deleted);
      $tr.find('td:nth-child(4)').text(task.created_at);
      $tr.find('td:nth-child(5)').text(task.modified_at);
    }
  }
});

// handle form submit
$(document).ready(($) => {
  $('[name="sync-posts"]').on('submit', (event) => {
    event.preventDefault();
    const $form = $(event.target);
    const $button = $form.find('input[type="submit"]');
    const formData = $form.serializeArray();
    formData.push({ name: '_nonce', value: window.ajax_nonce });
    $button.prop('disabled', true);
    $.ajax({
      url: window.ajax_url,
      method: 'POST',
      data: formData,
      success: (response) => {
        $button.prop('disabled', false);
        wp.heartbeat.connectNow();
        const intervalId = setInterval(() => wp.heartbeat.connectNow(), 10000);
        setTimeout(() => clearInterval(intervalId), 120000);
      },
      error: (response) => {
        $button.prop('disabled', false);
        const data = response.responseJSON.data;
        console.error(data);
        alert('[Failed] ' + data);
      },
    });
  });
});

})(jQuery);

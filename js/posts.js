(($) => {

const RECENT_TASKS_COLUMNS = [
  'status', 'uploaded', 'deleted', 'created_by', 'created_at', 'modified_at',
];

function escapeHtml(value) {
  return `${value}`.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// update recent tasks from heartbeat response
$(document).on('heartbeat-tick', (event, { miso_recent_tasks } = {}) => {
  for (const task of miso_recent_tasks) {
    const $tr = $('#recent-tasks tr[data-task-id="' + task.id + '"]');
    if ($tr.length === 0) {
      $('#recent-tasks tbody').prepend(`<tr data-task-id="${task.id}">${ RECENT_TASKS_COLUMNS.map(column => `<td class="column-columnname" data-column=${escapeHtml(column)}>${escapeHtml(task[column])}</td>`) }</tr>`);
    } else {
      for (const column of RECENT_TASKS_COLUMNS) {
        $tr.find(`td[data-column=${escapeHtml(column)}]`).text(task[column]);
      }
    }
  }
});

// handle form submit
$(document).ready(($) => {
  $('[name="sync-posts"]').on('submit', (event) => {
    event.preventDefault();
    const $form = $(event.target);
    const $button = $form.find('input[type="submit"]');
    const data = $form.serializeArray();
    data.push({ name: '_ajax_nonce', value: window.miso_sync_posts_form_context.ajax_nonce });
    $button.prop('disabled', true);
    $.ajax({
      url: window.miso_sync_posts_form_context.ajax_url,
      method: 'POST',
      data,
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

(($) => {

const RECENT_TASKS_COLUMNS = [
  'status', 'uploaded', 'deleted', 'created_by', 'created_at', 'modified_at',
];
const RECENT_TASKS_DETAILS = [
  { key: 'error', label: 'Error' },
];

function escapeHtml(value) {
  return `${value}`.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function hasDetails(task) {
  for (const column of RECENT_TASKS_DETAILS) {
    if (task[column.key]) {
      return true;
    }
  }
  return false;
}

function taskHtml(task) {
  return `<tr data-task-id="${task.id}"${ hasDetails(task) ? ' class="has-details"' : '' }>${taskHtmlColumns(task)}</tr>` + 
    `<tr class="task-details"><td class="column-columnname" colspan="${RECENT_TASKS_COLUMNS.length + 1}">${taskHtmlDetails(task)}</td></tr>`;
}

function taskHtmlColumns(task) {
  return RECENT_TASKS_COLUMNS.map(column => `<td class="column-columnname" data-column=${escapeHtml(column)}>${escapeHtml(task[column])}</td>`);
}

function taskHtmlDetails(task) {
  return RECENT_TASKS_DETAILS.map(column => `<section data-column="${escapeHtml(column.key)}"><h3>${escapeHtml(column.label)}</h3><div class="value">${escapeHtml(task[column.key])}</div></section>`);
}

function updateTaskTr($tr, task) {
  for (const column of RECENT_TASKS_COLUMNS) {
    $tr.find(`td[data-column=${escapeHtml(column)}]`).text(task[column]);
  }
  for (const column of RECENT_TASKS_DETAILS) {
    $tr.find(`section[data-column=${column.key}] .value`).text(task[column.key]);
  }
  $tr[hasDetail ? 'addClass' : 'removeClass']('has-details');
}

// update recent tasks from heartbeat response
$(document).on('heartbeat-tick', (event, { miso_recent_tasks } = {}) => {
  for (const task of miso_recent_tasks) {
    const $tr = $('#recent-tasks tr[data-task-id="' + task.id + '"]');
    if ($tr.length === 0) {
      $('#recent-tasks tbody').prepend(taskHtml(task));
    } else {
      updateTaskTr($tr, task);
    }
  }
});

$(document).ready(($) => {
  // handle form submit
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
  $('#recent-tasks').on('click', '[data-role="toggle-open"]', (event) => {
    const tr = event.target.closest('tr');
    tr && tr.classList.toggle('open');
  });
});

})(jQuery);

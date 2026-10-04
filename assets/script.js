document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Continue with this action?')) event.preventDefault();
    });
  });

  document.querySelectorAll('form[data-validate]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
      }
    });
  });
  document.addEventListener('click', (event) => {
    const dismiss = event.target.closest('[data-bs-dismiss="alert"]');
    if (dismiss) dismiss.closest('.alert')?.remove();
  });

  const materialType = document.querySelector('[data-material-type]');
  if (materialType) {
    const filePanel = document.querySelector('[data-material-file]');
    const urlPanel = document.querySelector('[data-material-url]');
    const fileInput = document.querySelector('#material_file');
    const urlInput = document.querySelector('#material_url');
    const syncMaterialType = () => {
      const isLink = materialType.value === 'link';
      filePanel.hidden = isLink;
      urlPanel.hidden = !isLink;
      fileInput.disabled = isLink;
      fileInput.required = !isLink;
      urlInput.disabled = !isLink;
      urlInput.required = isLink;
    };
    materialType.addEventListener('change', syncMaterialType);
    syncMaterialType();
  }

  ['roster-course', 'materials-course'].forEach((id) => {
    const select = document.getElementById(id);
    if (select) {
      select.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('course_id', select.value);
        window.location.href = url.toString();
      });
    }
  });

  const search = document.getElementById('user-search');
  const roleFilter = document.getElementById('role-filter');
  const departmentFilter = document.getElementById('department-filter');
  const tableBody = document.getElementById('users-table-body');
  const searchStatus = document.getElementById('search-status');
  if (search && roleFilter && departmentFilter && tableBody) {
    let timer;
    let controller;
    const updateUsers = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(async () => {
        if (controller) controller.abort();
        controller = new AbortController();
        const query = new URLSearchParams({ q: search.value, role: roleFilter.value, department: departmentFilter.value });
        try {
          const response = await fetch(`search_users.php?${query}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
          });
          if (!response.ok) throw new Error('Search failed');
          const result = await response.json();
          tableBody.innerHTML = result.html;
          tableBody.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
              if (!window.confirm(form.dataset.confirm || 'Continue with this action?')) event.preventDefault();
            });
          });
          if (searchStatus) searchStatus.textContent = `${result.count} matching accounts`;
        } catch (error) {
          if (error.name !== 'AbortError' && searchStatus) {
            searchStatus.textContent = 'Search is temporarily unavailable. Reload to restore the account list.';
          }
        }
      }, 180);
    };
    search.addEventListener('input', updateUsers);
    roleFilter.addEventListener('change', updateUsers);
    departmentFilter.addEventListener('change', updateUsers);
  }

  if (window.CAMPUS_TRACK_LIVE_ATTENDANCE) {
    const refreshAttendance = async () => {
      try {
        const response = await fetch('attendance_data.php', {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (!response.ok) return;
        const data = await response.json();
        document.querySelectorAll('[data-live-total]').forEach((node) => {
          node.textContent = Number(data.overall).toFixed(1);
        });
        for (const course of data.courses) {
          document.querySelectorAll(`[data-live-course="${course.id}"]`).forEach((node) => {
            node.textContent = `${Number(course.percentage).toFixed(1)}%`;
          });
          const meter = document.querySelector(`[data-live-course="${course.id}"]`)?.closest('.course-summary')?.querySelector('.meter-track span');
          if (meter) {
            meter.style.width = `${Math.min(100, Math.max(0, Number(course.percentage)))}%`;
            meter.classList.toggle('meter-low', Number(course.percentage) < 75);
          }
        }
      } catch {
        // Keep the last successful server-rendered summary when offline.
      }
    };
    refreshAttendance();
    window.setInterval(refreshAttendance, 60000);
  }

  const chart = document.getElementById('attendanceChart');
  if (chart) {
    let labels = [];
    let values = [];
    try {
      labels = JSON.parse(chart.dataset.chartLabels || '[]');
      values = JSON.parse(chart.dataset.chartValues || '[]').map(Number);
    } catch {
      labels = [];
      values = [];
    }
    if (window.Chart) {
      new Chart(chart, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Attendance',
            data: values,
            backgroundColor: values.map((value) => value < 75 ? '#eaa29d' : '#70c9b2'),
            borderRadius: 8,
            maxBarThickness: 48,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (context) => `${context.parsed.y.toFixed(1)}% attendance` } },
          },
          scales: {
            y: { min: 0, max: 100, ticks: { callback: (value) => `${value}%` }, grid: { color: '#edf0f3' } },
            x: { grid: { display: false } },
          },
        },
      });
    } else {
      drawOfflineChart(chart, labels, values);
    }
  }
});

function drawOfflineChart(canvas, labels, values) {
  const ratio = window.devicePixelRatio || 1;
  const width = canvas.clientWidth || 720;
  const height = 280;
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  const context = canvas.getContext('2d');
  context.scale(ratio, ratio);
  context.clearRect(0, 0, width, height);
  const left = 36;
  const top = 12;
  const bottom = 36;
  const chartHeight = height - top - bottom;
  const chartWidth = width - left - 16;
  context.font = '12px system-ui, sans-serif';
  context.textAlign = 'right';
  context.fillStyle = '#7c8997';
  [0, 25, 50, 75, 100].forEach((value) => {
    const y = top + chartHeight - (value / 100) * chartHeight;
    context.strokeStyle = '#edf0f3';
    context.beginPath();
    context.moveTo(left, y);
    context.lineTo(width - 8, y);
    context.stroke();
    context.fillText(`${value}%`, left - 8, y + 4);
  });
  const slot = chartWidth / Math.max(labels.length, 1);
  const barWidth = Math.min(48, slot * 0.54);
  labels.forEach((label, index) => {
    const value = Math.min(100, Math.max(0, values[index] || 0));
    const barHeight = chartHeight * value / 100;
    const x = left + slot * index + (slot - barWidth) / 2;
    const y = top + chartHeight - barHeight;
    context.fillStyle = value < 75 ? '#eaa29d' : '#70c9b2';
    context.beginPath();
    context.roundRect(x, y, barWidth, Math.max(barHeight, 2), 6);
    context.fill();
    context.textAlign = 'center';
    context.fillStyle = '#5e6b78';
    context.fillText(label, x + barWidth / 2, height - 12);
  });
}
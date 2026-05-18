import Chart from 'chart.js/auto';

const chartInstances = new WeakMap();

function getChartElement(target) {
  if (typeof target === 'string') {
    return document.getElementById(target) || document.querySelector(target);
  }

  return target instanceof HTMLElement ? target : null;
}

function getThemeMode() {
  const rootTheme = document.documentElement.getAttribute('data-bs-theme');
  const storedTheme = localStorage.getItem('theme');

  return rootTheme || storedTheme || 'light';
}

function getThemeColors() {
  if (getThemeMode() === 'dark') {
    return {
      border: '#91bbed',
      fill: 'rgba(145, 187, 237, 0.16)',
    };
  }

  return {
    border: '#206bc4',
    fill: 'rgba(32, 107, 196, 0.16)',
  };
}

function normalizeTimeSeriesData(data) {
  if (!Array.isArray(data)) {
    return [];
  }

  return data
    .filter(point => Array.isArray(point) && point.length >= 2)
    .map(point => ({
      x: Number(point[0]),
      y: Number(point[1]),
    }))
    .filter(point => Number.isFinite(point.x) && Number.isFinite(point.y));
}

export function renderTimeSeriesSparkline(target, data, options = {}) {
  const container = getChartElement(target);
  if (!container) {
    return null;
  }

  const existingChart = chartInstances.get(container);
  if (existingChart) {
    existingChart.destroy();
    chartInstances.delete(container);
  }

  const series = normalizeTimeSeriesData(data);
  const label = options.label || 'Series';
  const height = Number.isFinite(options.height) ? options.height : 140;
  const { border, fill } = getThemeColors();
  const firstPoint = series[0] || null;
  const lastPoint = series[series.length - 1] || null;

  container.replaceChildren();
  container.style.width = '100%';
  container.style.height = `${height}px`;

  const canvas = document.createElement('canvas');
  canvas.setAttribute('aria-label', label);
  canvas.setAttribute('role', 'img');
  container.appendChild(canvas);

  const chart = new Chart(canvas, {
    type: 'line',
    data: {
      datasets: [{
        label,
        data: series,
        parsing: false,
        borderColor: border,
        backgroundColor: fill,
        borderWidth: 2,
        tension: 0.4,
        cubicInterpolationMode: 'monotone',
        fill: true,
        pointRadius: 0,
        pointHoverRadius: 0,
        pointHitRadius: 8,
        borderCapStyle: 'round',
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      normalized: true,
      layout: {
        padding: 0,
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          enabled: false,
        },
      },
      scales: {
        x: {
          type: 'linear',
          bounds: 'data',
          display: false,
          min: firstPoint ? firstPoint.x : undefined,
          max: lastPoint ? lastPoint.x : undefined,
          offset: false,
          grid: {
            display: false,
            drawBorder: false,
          },
          border: {
            display: false,
          },
        },
        y: {
          display: false,
          beginAtZero: true,
          grace: 0,
          grid: {
            display: false,
            drawBorder: false,
          },
          border: {
            display: false,
          },
        },
      },
      elements: {
        line: {
          capBezierPoints: true,
        },
      },
    },
  });

  chartInstances.set(container, chart);

  return chart;
}

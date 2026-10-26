document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.fida-table, .erp-table').forEach(table => {
    table.classList.add('erp-responsive-list');
    const labels = Array.from(table.querySelectorAll('thead th'), th => th.textContent.trim());
    table.querySelectorAll('tbody tr').forEach(row => {
      const cells = Array.from(row.children).filter(el => el.tagName === 'TD');
      if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
        cells[0].classList.add('erp-empty-state');
        return;
      }
      cells.forEach((cell, index) => {
        if (!cell.hasAttribute('data-label')) cell.dataset.label = labels[index] || 'Details';
      });
    });
  });
});

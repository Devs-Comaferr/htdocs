(function () {
    var config = window.planificadorMenuConfig || {};
    var list = document.getElementById('cycleList');
    var hiddenInputsContainer = document.getElementById('cycleHiddenInputs');
    var form = document.getElementById('reiniciarCiclosForm');
    var modalElement = document.getElementById('reiniciarCiclosModal');
    var draggedItem = null;

    if (!list || !hiddenInputsContainer || !form) {
        return;
    }

    function updateCycleOrder() {
        var items = list.querySelectorAll('.cycle-item');
        hiddenInputsContainer.innerHTML = '';

        items.forEach(function (item, index) {
            var order = index + 1;
            var badge = item.querySelector('.cycle-order-badge');
            var codZona = item.getAttribute('data-cod-zona');
            var input = document.createElement('input');

            if (badge) {
                badge.textContent = order;
            }

            input.type = 'hidden';
            input.name = 'ordenes[' + codZona + ']';
            input.value = order;
            hiddenInputsContainer.appendChild(input);
        });
    }

    function moveItem(item, direction) {
        if (!item) {
            return;
        }

        var sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) {
            return;
        }

        if (direction === 'up') {
            list.insertBefore(item, sibling);
        } else {
            list.insertBefore(sibling, item);
        }

        updateCycleOrder();
    }

    list.querySelectorAll('.cycle-move-button').forEach(function (button) {
        button.addEventListener('click', function () {
            moveItem(button.closest('.cycle-item'), button.getAttribute('data-direction'));
        });
    });

    list.querySelectorAll('.cycle-item').forEach(function (item) {
        item.draggable = true;

        item.addEventListener('dragstart', function () {
            draggedItem = item;
            item.classList.add('dragging');
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('dragging');
            draggedItem = null;
            updateCycleOrder();
        });

        item.addEventListener('dragover', function (event) {
            event.preventDefault();
            if (!draggedItem || draggedItem === item) {
                return;
            }

            var rect = item.getBoundingClientRect();
            var before = event.clientY < rect.top + (rect.height / 2);
            list.insertBefore(draggedItem, before ? item : item.nextElementSibling);
        });
    });

    var pointerState = {
        item: null
    };

    list.querySelectorAll('.cycle-drag-handle').forEach(function (handle) {
        handle.addEventListener('pointerdown', function (event) {
            var item = handle.closest('.cycle-item');
            if (!item) {
                return;
            }

            pointerState.item = item;
            item.classList.add('dragging');
            handle.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        handle.addEventListener('pointermove', function (event) {
            var item = pointerState.item;
            if (!item) {
                return;
            }

            var target = document.elementFromPoint(event.clientX, event.clientY);
            if (!target) {
                return;
            }

            var overItem = target.closest('.cycle-item');
            if (!overItem || overItem === item || overItem.parentElement !== list) {
                return;
            }

            var rect = overItem.getBoundingClientRect();
            var before = event.clientY < rect.top + (rect.height / 2);
            list.insertBefore(item, before ? overItem : overItem.nextElementSibling);
            updateCycleOrder();
        });

        function releasePointer(event) {
            var item = pointerState.item;
            pointerState.item = null;
            if (item) {
                item.classList.remove('dragging');
            }
            if (handle.hasPointerCapture && handle.hasPointerCapture(event.pointerId)) {
                handle.releasePointerCapture(event.pointerId);
            }
            updateCycleOrder();
        }

        handle.addEventListener('pointerup', releasePointer);
        handle.addEventListener('pointercancel', releasePointer);
    });

    form.addEventListener('submit', updateCycleOrder);
    updateCycleOrder();

    if (config.abrirModalReiniciar && modalElement && window.bootstrap && window.bootstrap.Modal) {
        window.addEventListener('load', function () {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    }
})();

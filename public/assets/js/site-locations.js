'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const siteSelect = document.getElementById('sede_id');
    const parentSelect = document.getElementById('ubicacion_padre_id');

    if (
        !(siteSelect instanceof HTMLSelectElement)
        || !(parentSelect instanceof HTMLSelectElement)
    ) {
        return;
    }

    const initialParentId =
        parentSelect.dataset.selectedParent
        ?? parentSelect.value
        ?? '';

    const excludedLocationId =
        parentSelect.dataset.excludeId
        ?? '';

    const replaceOptions = (rows, selectedParentId) => {
        parentSelect.replaceChildren();

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'Sin padre';
        parentSelect.appendChild(emptyOption);

        for (const row of rows) {
            const option = document.createElement('option');
            option.value = String(row.id);
            option.textContent = `${row.codigo} - ${row.nombre}`;
            option.selected = option.value === selectedParentId;
            parentSelect.appendChild(option);
        }
    };

    const loadParents = async (preserveSelection) => {
        const siteId = siteSelect.value;

        if (siteId === '') {
            replaceOptions([], '');
            parentSelect.disabled = true;
            return;
        }

        parentSelect.disabled = true;

        const parameters = new URLSearchParams({
            sede_id: siteId,
        });

        if (excludedLocationId !== '') {
            parameters.set('excluir_id', excludedLocationId);
        }

        try {
            const response = await fetch(
                '/catalogos/ubicaciones?' + parameters.toString(),
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            if (!response.ok) {
                throw new Error('No fue posible cargar las ubicaciones.');
            }

            const payload = await response.json();
            const rows = Array.isArray(payload.data)
                ? payload.data
                : [];

            replaceOptions(
                rows,
                preserveSelection ? initialParentId : ''
            );
        } catch (error) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No fue posible cargar las ubicaciones';
            parentSelect.replaceChildren(option);
        } finally {
            parentSelect.disabled = false;
        }
    };

    siteSelect.addEventListener('change', () => {
        loadParents(false);
    });

    loadParents(true);
});

import * as FilePond from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginImageCrop from 'filepond-plugin-image-crop';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginImageResize from 'filepond-plugin-image-resize';
import FilePondPluginFilePoster from 'filepond-plugin-file-poster';

FilePond.registerPlugin(
    FilePondPluginImagePreview,
    FilePondPluginImageCrop,
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImageResize,
    FilePondPluginFilePoster,
);

export function initAuctionFilepond(input, options) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const pond = FilePond.create(input, {
        allowMultiple: true,
        allowReorder: true,
        maxFiles: options.maxFiles ?? 10,
        acceptedFileTypes: options.acceptedFileTypes ?? ['image/jpeg', 'image/png', 'image/webp'],
        maxFileSize: options.maxFileSize ?? '5MB',
        imageResizeTargetWidth: 1920,
        imageResizeTargetHeight: 1080,
        imageCropAspectRatio: '1:1',
        server: {
            process: {
                url: options.processUrl,
                method: 'POST',
                name: 'file',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                onload: (responseText) => {
                    const parsed = JSON.parse(responseText);
                    return String(parsed.id);
                },
            },
            revert: (uniqueFileId, load, error) => {
                fetch(options.deleteUrlTemplate.replace('__MEDIA_ID__', uniqueFileId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Failed to delete image');
                        }
                        load();
                    })
                    .catch(() => error('Delete failed'));
            },
        },
        files: (options.initialFiles ?? []).map((image) => ({
            source: String(image.id),
            options: {
                type: 'local',
                file: {
                    name: image.name,
                    size: image.size ?? 0,
                    type: image.mime_type ?? 'image/jpeg',
                },
                metadata: {
                    poster: image.thumbnail_url,
                },
            },
        })),
    });

    pond.on('reorderfiles', (files) => {
        const order = files
            .map((fileItem) => Number(fileItem.serverId))
            .filter((id) => Number.isInteger(id) && id > 0);

        if (order.length === 0) {
            return;
        }

        fetch(options.reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ order }),
        });
    });

    return pond;
}

window.initAuctionFilepond = initAuctionFilepond;

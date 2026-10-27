'use client';

import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import { ComponentCard } from '@/components/ui/component-card';
import { EyeIcon, TrashBinIcon, UploadIcon } from '@/components/ui/icon';
import { Modal } from '@/components/ui/modal';
import { useModal } from '@/hooks/useModal';
import Image from 'next/image';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';

type FileWithPreview = File & {
  preview?: string;
};

export function DropzoneCard() {
  const [files, setFiles] = useState<FileWithPreview[]>([]);
  const [previewImage, setPreviewImage] = useState<FileWithPreview | null>(null);
  const { isOpen, openModal, closeModal } = useModal();
  const filesRef = useRef<FileWithPreview[]>(files);

  useEffect(() => {
    filesRef.current = files;
  }, [files]);

  const onDrop = useCallback((acceptedFiles: File[]) => {
    setFiles((prevFiles) => [
      ...prevFiles,
      ...acceptedFiles.map((file) =>
        Object.assign(file, {
          preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : undefined
        })
      )
    ]);
  }, []);

  function removeFile(fileName: string) {
    setFiles((prevFiles) => {
      const target = prevFiles.find((file) => file.name === fileName);
      if (target?.preview) URL.revokeObjectURL(target.preview);
      return prevFiles.filter((file) => file.name !== fileName);
    });
  }

  function handleOpenPreview(file: FileWithPreview) {
    setPreviewImage(file);
    openModal();
  }

  function handleClosePreview() {
    closeModal();
    setPreviewImage(null);
  }

  useEffect(() => {
    return () => {
      filesRef.current.forEach((file) => {
        if (file.preview) URL.revokeObjectURL(file.preview);
      });
    };
  }, []);

  const { getRootProps, getInputProps, isDragActive, isDragAccept, isDragReject, isFocused } = useDropzone({
    onDrop,
    accept: {
      'image/png': ['.png'],
      'image/jpeg': ['.jpg', '.jpeg'],
      'image/webp': ['.webp'],
      'image/svg+xml': ['.svg']
    },
    maxSize: 5 * 1024 * 1024
  });

  function getBorderColor() {
    if (isDragReject) return 'border-error-500 bg-error-50/60 dark:bg-error-950/20';
    if (isDragAccept) return 'border-brand-500 bg-brand-50/60 dark:bg-brand-950/20';
    if (isFocused) return 'border-brand-500 ring-2 ring-brand-500/20 bg-gray-50 dark:bg-gray-900';
    return 'border-gray-300 bg-gray-50 hover:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-brand-500';
  }

  function formatFileSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  const dropTitle = isDragReject
    ? FORM_ELEMENTS_COPY.unsupported
    : isDragAccept
      ? FORM_ELEMENTS_COPY.dropImages
      : isDragActive
        ? FORM_ELEMENTS_COPY.dropHere
        : FORM_ELEMENTS_COPY.dragDrop;

  return (
    <ComponentCard title={FORM_ELEMENTS_COPY.dropzone}>
      <div>
        <div
          {...getRootProps()}
          className={`relative flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed p-7 text-center outline-hidden transition-all duration-200 lg:p-10 ${getBorderColor()}`}
        >
          <input {...getInputProps()} />
          <div className='flex flex-col items-center'>
            <div className='mb-4 flex size-15 items-center justify-center rounded-full bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300'>
              <UploadIcon className='size-6 text-current' />
            </div>
            <h4 className='text-theme-xl mb-2 font-semibold text-gray-800 dark:text-white/90'>{dropTitle}</h4>
            <p className='mb-4 max-w-72.5 text-sm text-gray-600 dark:text-gray-400'>
              {isDragReject ? FORM_ELEMENTS_COPY.dropRejectHint : FORM_ELEMENTS_COPY.dropHint}
            </p>
            <span className='text-theme-sm font-medium text-brand-500 underline hover:text-brand-600'>
              {FORM_ELEMENTS_COPY.browse}
            </span>
          </div>
        </div>

        {files.length > 0 ? (
          <div className='mt-6 space-y-4'>
            <div className='flex items-center justify-between'>
              <h5 className='text-theme-sm font-medium text-gray-700 dark:text-gray-300'>
                {FORM_ELEMENTS_COPY.uploaded} ({files.length})
              </h5>
              <button
                type='button'
                onClick={() => {
                  files.forEach((file) => {
                    if (file.preview) URL.revokeObjectURL(file.preview);
                  });
                  setFiles([]);
                }}
                className='text-theme-xs font-medium text-error-500 hover:text-error-600 dark:text-error-400'
              >
                {FORM_ELEMENTS_COPY.clearAll}
              </button>
            </div>

            <div className='grid grid-cols-2 gap-4 sm:grid-cols-3'>
              {files.map((file) => (
                <div
                  key={`${file.name}-${file.lastModified}`}
                  className='group relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-xs transition hover:shadow-theme-sm dark:border-gray-800 dark:bg-gray-900'
                >
                  <div className='relative aspect-4/3 w-full overflow-hidden bg-gray-100 dark:bg-gray-800'>
                    {file.preview ? (
                      <Image
                        src={file.preview}
                        alt={file.name}
                        fill
                        unoptimized
                        className='object-cover transition-transform duration-300 group-hover:scale-105'
                      />
                    ) : (
                      <div className='flex h-full w-full items-center justify-center text-gray-400'>
                        <UploadIcon className='size-8' />
                      </div>
                    )}
                    <div className='absolute inset-0 flex items-center justify-center gap-2 bg-gray-900/60 opacity-0 transition-opacity duration-200 group-hover:opacity-100'>
                      {file.preview ? (
                        <button
                          type='button'
                          onClick={() => handleOpenPreview(file)}
                          className='flex size-8 items-center justify-center rounded-full bg-white/90 text-gray-800 backdrop-blur-xs transition hover:bg-white dark:bg-gray-800/90 dark:text-white dark:hover:bg-gray-800'
                          title={FORM_ELEMENTS_COPY.preview}
                          aria-label={`${FORM_ELEMENTS_COPY.preview} ${file.name}`}
                        >
                          <EyeIcon className='size-4' />
                        </button>
                      ) : null}
                      <button
                        type='button'
                        onClick={() => removeFile(file.name)}
                        className='flex size-8 items-center justify-center rounded-full bg-white/90 text-error-500 backdrop-blur-xs transition hover:bg-white dark:bg-gray-800/90 dark:text-error-400 dark:hover:bg-gray-800'
                        title={FORM_ELEMENTS_COPY.clearAll}
                        aria-label={`${FORM_ELEMENTS_COPY.clearAll} ${file.name}`}
                      >
                        <TrashBinIcon className='size-4' />
                      </button>
                    </div>
                  </div>
                  <div className='p-3'>
                    <p className='text-theme-xs truncate font-medium text-gray-800 dark:text-white/90' title={file.name}>
                      {file.name}
                    </p>
                    <p className='mt-0.5 text-[11px] text-gray-500 dark:text-gray-400'>{formatFileSize(file.size)}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : null}

        <Modal isOpen={isOpen} onClose={handleClosePreview} className='max-w-2xl p-6 sm:p-8'>
          {previewImage ? (
            <div>
              <div className='mb-4'>
                <h4 className='text-theme-lg font-semibold text-gray-800 dark:text-white/90'>{FORM_ELEMENTS_COPY.preview}</h4>
                <p className='text-theme-xs text-gray-500 dark:text-gray-400'>
                  {previewImage.name} • {formatFileSize(previewImage.size)}
                </p>
              </div>
              <div className='relative aspect-video w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-800 dark:bg-gray-800'>
                {previewImage.preview ? (
                  <Image src={previewImage.preview} alt={previewImage.name} fill unoptimized className='object-contain' />
                ) : null}
              </div>
            </div>
          ) : null}
        </Modal>
      </div>
    </ComponentCard>
  );
}

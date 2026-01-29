import React, { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

export default function Modal({
 show,
 onClose,
 title,
 children,
 maxWidth = 'max-w-md',
 showCloseButton = true
}) {
 return (
  <Transition appear show={show} as={Fragment}>
   <Dialog as="div" className="relative z-50" onClose={onClose}>
    <Transition.Child
     as={Fragment}
     enter="ease-out duration-300"
     enterFrom="opacity-0"
     enterTo="opacity-100"
     leave="ease-in duration-200"
     leaveFrom="opacity-100"
     leaveTo="opacity-0"
    >
     <div className="fixed inset-0 bg-black bg-opacity-25" />
    </Transition.Child>

    <div className="fixed inset-0 overflow-y-auto">
     <div className="flex min-h-full items-center justify-center p-4 text-center">
      <Transition.Child
       as={Fragment}
       enter="ease-out duration-300"
       enterFrom="opacity-0 scale-95"
       enterTo="opacity-100 scale-100"
       leave="ease-in duration-200"
       leaveFrom="opacity-100 scale-100"
       leaveTo="opacity-0 scale-95"
      >
       <Dialog.Panel className={`w-full ${maxWidth} transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all`}>
        {title && (
         <div className="flex items-center justify-between mb-4">
          <Dialog.Title
           as="h3"
           className="text-lg font-medium leading-6 text-gray-900"
          >
           {title}
          </Dialog.Title>
          {showCloseButton && (
           <button
            type="button"
            className="rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            onClick={onClose}
           >
            <XMarkIcon className="h-6 w-6" />
           </button>
          )}
         </div>
        )}

        {children}
       </Dialog.Panel>
      </Transition.Child>
     </div>
    </div>
   </Dialog>
  </Transition>
 );
}

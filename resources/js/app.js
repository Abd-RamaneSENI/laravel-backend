import React from 'react';
import { createRoot } from 'react-dom/client';
import { LibraryApp } from './library-app';
import '../css/library.css';
import '../css/library-editor.css';

const root = document.getElementById('library-root');

if (root) {
    createRoot(root).render(React.createElement(React.StrictMode, null, React.createElement(LibraryApp)));
}

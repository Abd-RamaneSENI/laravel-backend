import { useEffect, useMemo, useState } from 'react';
import { BookOpen, CalendarClock, CircleUserRound, Download, FilePlus, FileText, LayoutDashboard, Library, LogIn, LogOut, Pencil, Plus, Search, ShieldCheck, Tag, Trash2, Users, X } from 'lucide-react';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

async function api(path, options = {}) {
    const response = await fetch(`/api${path}`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
        ...options,
    });
    if (response.status === 204) return null;
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : null;
        throw new Error(errors || payload.message || 'Une erreur est survenue.');
    }
    return payload;
}

const formatDate = (value) => value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(value)) : '—';
const formatDateTime = (value) => value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
const formatPrice = (amount, currency = 'XOF') => new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount);
const borrowingFee = (book) => Math.ceil((Number(book?.price) || 0) * 0.05);
const reservationLabels = { waiting: 'En attente', ready: 'Prête à emprunter', fulfilled: 'Transformée en emprunt', cancelled: 'Annulée' };

function Cover({ book, compact = false }) {
    return book.cover_url ? <img className="book-cover-image" src={book.cover_url} alt={`Couverture de ${book.title}`} /> : (
        <div className={`book-cover-art ${compact ? 'compact' : ''}`} aria-hidden="true">
            <BookOpen size={compact ? 28 : 42} />
            <span>{book.category?.name || 'Livre'}</span>
        </div>
    );
}

function Notice({ notice, dismiss }) {
    if (!notice) return null;
    return <div className={`notice ${notice.type}`} role="status"><span>{notice.message}</span><button type="button" onClick={dismiss} aria-label="Fermer le message"><X size={17} /></button></div>;
}

function AuthPanel({ mode, onClose, onAuthenticated, notify }) {
    const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [busy, setBusy] = useState(false);
    const register = mode === 'register';
    const submit = async (event) => {
        event.preventDefault();
        setBusy(true);
        try {
            const result = await api(register ? '/auth/register' : '/auth/login', { method: 'POST', body: JSON.stringify(form) });
            onAuthenticated(result.user);
            notify('success', register ? 'Votre compte est prêt.' : `Bon retour, ${result.user.name}.`);
        } catch (error) { notify('error', error.message); } finally { setBusy(false); }
    };
    return <aside className="side-panel" aria-label={register ? 'Créer un compte' : 'Se connecter'}>
        <div className="panel-heading"><div><p className="kicker">Espace personnel</p><h2>{register ? 'Créer un compte' : 'Connexion'}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label="Fermer"><X size={20} /></button></div>
        <form className="form-stack" onSubmit={submit}>
            {register && <label>Nom complet<input required maxLength="100" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>}
            <label>Adresse e-mail<input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
            <label>Mot de passe<input required type="password" minLength="12" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label>
            {register && <label>Confirmer le mot de passe<input required type="password" minLength="12" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} /></label>}
            <button className="primary-button" disabled={busy}>{busy ? 'Traitement...' : register ? 'Créer mon compte' : 'Se connecter'}</button>
        </form>
    </aside>;
}

function BookForm({ metadata, book, onClose, onMetadataChanged, onSaved, notify }) {
    const editing = Boolean(book);
    const [form, setForm] = useState({ title: book?.title || '', author_id: String(book?.author_id || ''), book_category_id: String(book?.book_category_id || ''), isbn: book?.isbn || '', published_year: book?.published_year || '', shelf_location: book?.shelf_location || '', price: String(book?.price ?? 10000), total_copies: String(book?.total_copies || 1), description: book?.description || '', is_active: book?.is_active ?? true });
    const [busy, setBusy] = useState(false);
    const [addingAuthor, setAddingAuthor] = useState(false);
    const [authorForm, setAuthorForm] = useState({ name: '', biography: '' });
    const change = (field) => (event) => setForm({ ...form, [field]: event.target.value });
    const addAuthor = async () => {
        if (!authorForm.name.trim()) return;
        try {
            const result = await api('/authors', { method: 'POST', body: JSON.stringify(authorForm) });
            const refreshed = await api('/metadata');
            onMetadataChanged(refreshed);
            setForm({ ...form, author_id: String(result.author.id) });
            setAuthorForm({ name: '', biography: '' });
            setAddingAuthor(false);
            notify('success', 'Auteur ajouté et sélectionné.');
        } catch (error) { notify('error', error.message); }
    };
    const submit = async (event) => {
        event.preventDefault(); setBusy(true);
        try {
            const payload = { ...form, author_id: Number(form.author_id), book_category_id: Number(form.book_category_id), price: Number(form.price || 0), total_copies: Number(form.total_copies), published_year: form.published_year ? Number(form.published_year) : null };
            const result = await api(editing ? `/books/${book.id}` : '/books', { method: editing ? 'PUT' : 'POST', body: JSON.stringify(payload) });
            onSaved(result.book); notify('success', editing ? 'Livre mis à jour.' : 'Livre ajouté au catalogue.'); onClose();
        } catch (error) { notify('error', error.message); } finally { setBusy(false); }
    };
    return <aside className="side-panel wide" aria-label={editing ? 'Modifier le livre' : 'Ajouter un livre'}><div className="panel-heading"><div><p className="kicker">Administration</p><h2>{editing ? 'Modifier le livre' : 'Nouveau livre'}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label="Fermer"><X size={20} /></button></div>
        <form className="form-stack two-columns" onSubmit={submit}>
            <label className="full">Titre<input required value={form.title} onChange={change('title')} /></label>
            <label>Auteur<div className="select-with-action"><select required value={form.author_id} onChange={change('author_id')}><option value="">Sélectionner</option>{metadata.authors.map((author) => <option key={author.id} value={author.id}>{author.name}</option>)}</select><button className="icon-button mini-action" type="button" title="Ajouter un auteur" aria-label="Ajouter un auteur" onClick={() => setAddingAuthor(!addingAuthor)}><Plus size={16} /></button></div></label>
            <label>Catégorie<select required value={form.book_category_id} onChange={change('book_category_id')}><option value="">Sélectionner</option>{metadata.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
            {addingAuthor && <div className="full quick-create"><strong>Nouvel auteur</strong><input placeholder="Nom de l'auteur" value={authorForm.name} onChange={(event) => setAuthorForm({ ...authorForm, name: event.target.value })} /><input placeholder="Biographie facultative" value={authorForm.biography} onChange={(event) => setAuthorForm({ ...authorForm, biography: event.target.value })} /><button type="button" className="secondary-button" onClick={addAuthor}>Ajouter l'auteur</button></div>}
            <label>ISBN<input value={form.isbn} onChange={change('isbn')} /></label>
            <label>Année<input type="number" min="1500" max="2100" value={form.published_year} onChange={change('published_year')} /></label>
            <label>Emplacement<input value={form.shelf_location} onChange={change('shelf_location')} placeholder="Ex. A-03" /></label>
            <label>Prix de référence (XOF)<input required type="number" min="0" value={form.price} onChange={change('price')} /></label>
            <label>Exemplaires<input required type="number" min="1" value={form.total_copies} onChange={change('total_copies')} /></label>
            <label className="full">Description<textarea rows="4" value={form.description} onChange={change('description')} /></label>
            <label className="full inline-check"><input type="checkbox" checked={form.is_active} onChange={(event) => setForm({ ...form, is_active: event.target.checked })} />Visible dans le catalogue</label>
            <div className="full form-actions"><button type="button" className="secondary-button" onClick={onClose}>Annuler</button><button className="primary-button" disabled={busy}>{busy ? 'Enregistrement...' : editing ? 'Enregistrer' : 'Ajouter le livre'}</button></div>
        </form>
    </aside>;
}

function BookCard({ book, user, documentCount, onLoan, onReserve, onPurchase, onEdit, onAddDocument, selectBook }) {
    const available = book.available_copies > 0;
    return <article className="book-card"><button className="book-card-main" type="button" onClick={() => selectBook(book)}><Cover book={book} compact /><div className="book-copy"><p className="category-label">{book.category?.name}</p><h3>{book.title}</h3><p>{book.author?.name}</p><small className="book-price">Prix {formatPrice(book.price || 0)} · Emprunt 5 % {formatPrice(borrowingFee(book))}</small>{documentCount > 0 && <small className="book-document-count"><FileText size={14} />{documentCount} document{documentCount > 1 ? 's' : ''} à lire</small>}</div></button><div className="book-card-footer"><span className={available ? 'availability available' : 'availability unavailable'}>{available ? `${book.available_copies} disponible${book.available_copies > 1 ? 's' : ''}` : 'Indisponible'}</span><div className="book-card-actions">{user && <button className="quiet-action" type="button" onClick={() => available ? onLoan(book) : onReserve(book)}>{available ? 'Emprunter 5 %' : 'Réserver'}</button>}{user && documentCount > 0 && <button className="quiet-action" type="button" onClick={() => onPurchase(book)}><Download size={15} />Acheter</button>}{user?.is_admin && <><button className="quiet-action add-book-document" type="button" onClick={() => onAddDocument(book)}><FilePlus size={16} />Ajouter un PDF</button><button className="icon-button edit-book" title="Modifier le livre" aria-label={`Modifier ${book.title}`} type="button" onClick={() => onEdit(book)}><Pencil size={16} /></button></>}</div></div></article>;
}

function LoanEntry({ loanItem, user, onPayFee, onReturn, onReadDocument, onHideReturned }) {
    const paid = loanItem.fee_status === 'paid';
    const active = !loanItem.returned_at && loanItem.status !== 'returned';
    const documents = loanItem.book?.reading_documents || [];
    const feeAmount = loanItem.fee_amount || borrowingFee(loanItem.book);
    const accessActive = active && paid && loanItem.access_expires_at && new Date(loanItem.access_expires_at) > new Date();
    const returned = Boolean(loanItem.returned_at || loanItem.status === 'returned');
    return <div className={`loan-entry ${paid ? 'fee-paid' : 'fee-pending'}`}><Cover book={loanItem.book} compact /><div><strong>{loanItem.book.title}</strong><p>{user.is_admin && `${loanItem.user.name} · `}Retour prévu le {formatDate(loanItem.due_at)}</p><p className="loan-fee-line">Frais d'emprunt 5 % : {formatPrice(feeAmount, loanItem.fee_currency || 'XOF')} · {paid ? 'Payé' : 'À payer'}</p>{paid && loanItem.access_expires_at && <p className="loan-fee-line">Lecture en ligne valable jusqu'au {formatDate(loanItem.access_expires_at)}</p>}{returned && <p className="loan-fee-line">Facturé : {formatPrice(loanItem.fee_charged_amount || 0, loanItem.fee_currency || 'XOF')} · Remboursement : {formatPrice(loanItem.fee_refund_amount || 0, loanItem.fee_currency || 'XOF')} ({loanItem.fee_refund_status || 'none'})</p>}{active && !paid && <p className="loan-reading-lock">Lecture en ligne bloquée jusqu'au paiement des frais.</p>}{active && paid && !accessActive && <p className="loan-reading-lock">Le délai de lecture de 30 jours est expiré. Empruntez à nouveau pour relire ce document.</p>}{accessActive && documents.length > 0 && <div className="loan-reading-actions">{documents.map((document) => <button key={document.id} className="quiet-action" type="button" onClick={() => onReadDocument(document)}><FileText size={16} />Lire en ligne</button>)}</div>}</div><span className={`loan-status ${returned ? 'returned' : 'borrowed'}`}>{returned ? 'Retourné' : 'En cours'}</span><div className="loan-actions">{!user.is_admin && active && !paid && <button className="primary-button" type="button" onClick={() => onPayFee(loanItem)}>Payer les 5 %</button>}{active && <button className="quiet-action" type="button" onClick={() => onReturn(loanItem)}>{user.is_admin ? 'Retour' : 'Retourner'}</button>}{returned && !user.is_admin && <button className="quiet-action" type="button" onClick={() => onHideReturned(loanItem)}><Trash2 size={15} />Retirer de ma liste</button>}</div></div>;
}

function ReservationEntry({ reservation, user, onCancel, onBorrow }) {
    const ready = reservation.status === 'ready';
    const waiting = reservation.status === 'waiting';
    const feeAmount = borrowingFee(reservation.book);
    return <div className="table-row reservation-entry"><div><strong>{reservation.book?.title}</strong><small>{user.is_admin && reservation.user ? `${reservation.user.name} · ` : ''}{reservation.book?.author?.name || 'Auteur non renseigné'} · Frais d'emprunt 5 % {formatPrice(feeAmount)}</small></div><span className={`reservation-status ${reservation.status}`}>{reservationLabels[reservation.status] || reservation.status}</span><div className="reservation-actions">{ready && !user.is_admin && <button className="primary-button" type="button" onClick={() => onBorrow(reservation)}>Emprunter ce livre</button>}{waiting && !user.is_admin && <button className="text-button danger" type="button" onClick={() => onCancel(reservation)}>Annuler</button>}</div></div>;
}

function Dashboard({ user, dashboard, loans, onReturn, onReservationStatus, onOpenAdminLoan }) {
    if (!dashboard) return <section className="dashboard-placeholder">Chargement de votre tableau de bord...</section>;
    const metricItems = Object.entries(dashboard.metrics);
    return <section className="dashboard"><div className="section-title"><div><p className="kicker">Tableau de bord</p><h2>{dashboard.kind === 'admin' ? 'Vue administration' : `Bonjour ${user.name}`}</h2></div></div><div className="metric-grid">{metricItems.map(([label, value]) => <div className="metric" key={label}><span>{label.replaceAll('_', ' ')}</span><strong>{value}</strong></div>)}</div>
        {dashboard.kind === 'admin' && <div className="data-section"><div className="data-section-title"><h3>Emprunts en cours</h3><div className="section-actions"><span>{loans.length}</span><button className="primary-button compact-button" type="button" onClick={onOpenAdminLoan}><Plus size={16} /> Enregistrer un emprunt</button></div></div><div className="table-list">{loans.slice(0, 8).map((loan) => <div className="table-row" key={loan.id}><div><strong>{loan.book.title}</strong><small>{loan.user.name} · Retour prévu {formatDate(loan.due_at)}</small></div>{!loan.returned_at && <button className="quiet-action" type="button" onClick={() => onReturn(loan)}>Enregistrer le retour</button>}</div>)}{loans.length === 0 && <p className="empty-copy">Aucun emprunt à traiter.</p>}</div></div>}
        {dashboard.kind === 'admin' && <div className="data-section"><div className="data-section-title"><h3>Réservations à traiter</h3><span>{dashboard.reservations?.length || 0}</span></div><div className="table-list">{dashboard.reservations?.map((reservation) => <div className="table-row" key={reservation.id}><div><strong>{reservation.book?.title}</strong><small>{reservation.user?.name} · {reservation.status === 'ready' ? 'Prête à retirer' : 'En attente'}</small></div><div className="reservation-actions">{reservation.status === 'waiting' && <button className="quiet-action" type="button" onClick={() => onReservationStatus(reservation, 'ready')}>Prête</button>}{reservation.status === 'ready' && <button className="quiet-action" type="button" onClick={() => onReservationStatus(reservation, 'waiting')}>En attente</button>}<button className="text-button danger" type="button" onClick={() => onReservationStatus(reservation, 'cancelled')}>Annuler</button></div></div>)}{!dashboard.reservations?.length && <p className="empty-copy">Aucune réservation à traiter.</p>}</div></div>}
        {dashboard.kind === 'admin' && <div className="data-section"><div className="data-section-title"><h3>Lecteurs actuellement en ligne</h3><span>{dashboard.reading_presences?.length || 0}</span></div><div className="table-list">{dashboard.reading_presences?.map((presence) => <div className="table-row" key={presence.id}><div><strong>{presence.user?.name}</strong><small>{presence.user?.email} · Lecture : {presence.document?.title}</small></div><small>Actif le {formatDateTime(presence.last_seen_at)}</small></div>)}{!dashboard.reading_presences?.length && <p className="empty-copy">Aucun lecteur en ligne pour le moment.</p>}</div></div>}
    </section>;
}

function AdminLoanForm({ books, reservations, onClose, onCreated, notify }) {
    const [members, setMembers] = useState([]);
    const [form, setForm] = useState({ user_id: '', book_id: '', due_days: '30', reservation_id: '' });
    const [busy, setBusy] = useState(false);
    useEffect(() => { api('/members').then((result) => setMembers(result.members || [])).catch((error) => notify('error', error.message)); }, []);
    const update = (event) => setForm({ ...form, [event.target.name]: event.target.value });
    const selectReservation = (event) => {
        const reservation = reservations.find((item) => String(item.id) === event.target.value);
        setForm({ ...form, reservation_id: event.target.value, user_id: reservation ? String(reservation.user_id) : form.user_id, book_id: reservation ? String(reservation.book_id) : form.book_id });
    };
    const submit = async (event) => {
        event.preventDefault(); setBusy(true);
        try {
            await api('/admin/loans', { method: 'POST', body: JSON.stringify({ user_id: Number(form.user_id), book_id: Number(form.book_id), due_days: Number(form.due_days), reservation_id: form.reservation_id ? Number(form.reservation_id) : null }) });
            notify('success', 'Emprunt enregistré.'); onCreated();
        } catch (error) { notify('error', error.message); } finally { setBusy(false); }
    };
    const availableBooks = books.filter((book) => book.is_active && book.available_copies > 0);
    return <aside className="side-panel" aria-label="Enregistrer un emprunt"><div className="panel-heading"><div><p className="kicker">Administration</p><h2>Enregistrer un emprunt</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label="Fermer"><X size={20} /></button></div><p className="panel-note">Une réservation sélectionnée renseigne automatiquement le lecteur et le livre.</p><form className="form-stack" onSubmit={submit}><label>Réservation associée (facultatif)<select value={form.reservation_id} onChange={selectReservation}><option value="">Aucune réservation</option>{reservations.map((reservation) => <option key={reservation.id} value={reservation.id}>{reservation.book?.title} · {reservation.user?.name}</option>)}</select></label><label>Lecteur<select name="user_id" value={form.user_id} onChange={update} required><option value="">Sélectionner un lecteur</option>{members.map((member) => <option key={member.id} value={member.id}>{member.name} · {member.email}</option>)}</select></label><label>Livre disponible<select name="book_id" value={form.book_id} onChange={update} required><option value="">Sélectionner un livre</option>{availableBooks.map((book) => <option key={book.id} value={book.id}>{book.title} ({book.available_copies} disponible{book.available_copies > 1 ? 's' : ''})</option>)}</select></label><label>Durée du prêt (jours)<input type="number" name="due_days" min="1" max="30" value={form.due_days} onChange={update} required /></label><div className="form-actions"><button className="secondary-button" type="button" onClick={onClose}>Annuler</button><button className="primary-button" disabled={busy}>{busy ? 'Enregistrement...' : 'Enregistrer'}</button></div></form></aside>;
}

function ReadingDocumentForm({ book, onClose, onSaved, notify }) {
    const [form, setForm] = useState({ title: '', description: '', file: null, is_active: true });
    const [busy, setBusy] = useState(false);
    const submit = async (event) => {
        event.preventDefault();
        if (!form.file) { notify('error', 'Sélectionnez un fichier PDF.'); return; }
        setBusy(true);
        const data = new FormData();
        data.append('title', form.title);
        data.append('description', form.description);
        data.append('file', form.file);
        if (book) data.append('book_id', String(book.id));
        data.append('is_active', form.is_active ? '1' : '0');
        try {
            await api('/reading-documents', { method: 'POST', body: data });
            notify('success', 'Document ajouté à la consultation privée.'); onSaved();
        } catch (error) { notify('error', error.message); } finally { setBusy(false); }
    };
    return <aside className="side-panel" aria-label="Ajouter un document de consultation"><div className="panel-heading"><div><p className="kicker">Administration</p><h2>{book ? 'Ajouter un PDF au livre' : 'Nouveau document'}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label="Fermer"><X size={20} /></button></div>{book && <p className="document-book-context"><BookOpen size={17} />{book.title}</p>}<p className="panel-note">PDF privé, consultable dans le lecteur de la bibliothèque. Aucun bouton de téléchargement n'est proposé.</p><form className="form-stack" onSubmit={submit}><label>Titre<input required maxLength="255" value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} /></label><label>Description<textarea rows="4" maxLength="2000" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /></label><label>Fichier PDF<input required type="file" accept="application/pdf" onChange={(event) => setForm({ ...form, file: event.target.files?.[0] || null })} /></label><label className="inline-check"><input type="checkbox" checked={form.is_active} onChange={(event) => setForm({ ...form, is_active: event.target.checked })} />Visible dans la bibliothèque</label><div className="form-actions"><button className="secondary-button" type="button" onClick={onClose}>Annuler</button><button className="primary-button" disabled={busy}>{busy ? 'Ajout...' : 'Ajouter le document'}</button></div></form></aside>;
}

function PaidResourceCard({ resource }) {
    return <article className="paid-resource-card">
        <div className="paid-resource-preview"><img src={resource.preview_url} alt={`Première page de ${resource.title}`} onError={(event) => { event.currentTarget.hidden = true; }} /><FileText size={27} /></div>
        <div className="paid-resource-copy"><p className="category-label">{resource.resource_type?.name || 'Ressource numérique'}</p><h3>{resource.title}</h3><p>{resource.description}</p><small>{resource.school_class?.cycle?.name}{resource.school_class ? ` · ${resource.school_class.name}` : ''}{resource.subject ? ` · ${resource.subject.name}` : ''}</small></div>
        <div className="paid-resource-action"><strong>{formatPrice(resource.price, resource.currency)}</strong><a className="quiet-action" href={`/catalogue/${resource.slug}`}>Payer le document</a></div>
    </article>;
}

function AdminAddBookButton({ onClick }) {
    return <button className="primary-button" type="button" onClick={onClick}><Plus size={18} />Ajouter un livre</button>;
}

const roleLabels = { student: 'Étudiant', vendor: 'Auteur / vendeur', admin: 'Administrateur', super_admin: 'Super-administrateur' };

function UserManagement({ users, currentUser, onRoleChange, onDelete }) {
    return <section className="dashboard user-management"><div className="section-title"><div><p className="kicker">Administration</p><h2>Utilisateurs</h2></div><span>{users.length}</span></div><p className="panel-note">Attribuez un rôle ou supprimez les comptes qui ne possèdent aucun historique.</p><div className="table-list">{users.map((managedUser) => {
        const isCurrentUser = managedUser.id === currentUser.id;
        return <div className="table-row user-row" key={managedUser.id}><div><strong>{managedUser.name}</strong><small>{managedUser.email}{isCurrentUser ? ' · Votre compte' : ''}</small></div><div className="user-management-actions">{isCurrentUser ? <span className="role-badge">{roleLabels[managedUser.role] || managedUser.role}</span> : <select value={managedUser.role} onChange={(event) => onRoleChange(managedUser, event.target.value)} aria-label={`Rôle de ${managedUser.name}`}><option value="student">Étudiant</option><option value="vendor">Auteur / vendeur</option><option value="admin">Administrateur</option></select>}<button className="icon-button delete-user" type="button" title="Supprimer l'utilisateur" aria-label={`Supprimer ${managedUser.name}`} disabled={isCurrentUser} onClick={() => onDelete(managedUser)}><Trash2 size={17} /></button></div></div>;
    })}</div></section>;
}

export function LibraryApp() {
    const [user, setUser] = useState(null);
    const [books, setBooks] = useState([]);
    const [paidResources, setPaidResources] = useState([]);
    const [readingDocuments, setReadingDocuments] = useState([]);
    const [managedUsers, setManagedUsers] = useState([]);
    const [metadata, setMetadata] = useState({ authors: [], categories: [] });
    const [dashboard, setDashboard] = useState(null);
    const [loans, setLoans] = useState([]);
    const [reservations, setReservations] = useState([]);
    const [view, setView] = useState('catalog');
    const [panel, setPanel] = useState(null);
    const [editingBook, setEditingBook] = useState(null);
    const [documentBook, setDocumentBook] = useState(null);
    const [selectedBook, setSelectedBook] = useState(null);
    const [selectedReadingDocument, setSelectedReadingDocument] = useState(null);
    const [notice, setNotice] = useState(null);
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState(() => ({ q: new URLSearchParams(window.location.search).get('q') || '', cycle: new URLSearchParams(window.location.search).get('cycle') || '', category: '', available: false }));
    const notify = (type, message) => { setNotice({ type, message }); window.setTimeout(() => setNotice(null), 4500); };
    const loadBooks = async (currentFilters = filters) => {
        const query = new URLSearchParams(); if (currentFilters.q) query.set('q', currentFilters.q); if (currentFilters.category) query.set('category', currentFilters.category); if (currentFilters.available) query.set('available', '1');
        const result = await api(`/books?${query}`); setBooks(result.data);
    };
    const loadPaidResources = async (currentFilters = filters) => {
        const query = new URLSearchParams(); if (currentFilters.q) query.set('q', currentFilters.q); if (currentFilters.cycle) query.set('cycle', currentFilters.cycle);
        const result = await api(`/resources?${query}`); setPaidResources(result.resources || []);
    };
    const loadReadingDocuments = async () => {
        const result = await api('/reading-documents'); setReadingDocuments(result.documents || []);
    };
    const loadManagedUsers = async (currentUser = user) => {
        if (!currentUser?.is_admin) { setManagedUsers([]); return; }
        const result = await api('/users'); setManagedUsers(result.users || []);
    };
    const loadMemberData = async (currentUser) => {
        if (!currentUser) return;
        const [dash, loanData, reservationData] = await Promise.all([api('/dashboard'), api('/loans'), api('/reservations')]);
        setDashboard(dash); setLoans(loanData.data); setReservations(reservationData.data);
    };
    useEffect(() => { (async () => { try { const [session, meta] = await Promise.all([api('/auth/me'), api('/metadata')]); setUser(session.user); setMetadata(meta); await Promise.all([loadBooks(filters), loadPaidResources(filters), loadReadingDocuments()]); if (session.user) await Promise.all([loadMemberData(session.user), loadManagedUsers(session.user)]); } catch (error) { notify('error', 'Impossible de charger la bibliothèque.'); } finally { setLoading(false); } })(); }, []);
    useEffect(() => {
        if (loading || !window.location.hash) return;
        window.requestAnimationFrame(() => document.getElementById(window.location.hash.slice(1))?.scrollIntoView({ block: 'start' }));
    }, [loading]);
    useEffect(() => {
        if (!selectedReadingDocument) return undefined;
        const documentId = selectedReadingDocument.id;
        const touchPresence = () => api(`/reading-documents/${documentId}/presence`, { method: 'POST' }).catch(() => {});
        touchPresence();
        const heartbeat = window.setInterval(touchPresence, 60000);
        return () => {
            window.clearInterval(heartbeat);
            api(`/reading-documents/${documentId}/presence`, { method: 'DELETE' }).catch(() => {});
        };
    }, [selectedReadingDocument]);
    useEffect(() => {
        if (!user?.is_admin || view !== 'dashboard') return undefined;
        const refresh = window.setInterval(() => loadMemberData(user).catch(() => {}), 30000);
        return () => window.clearInterval(refresh);
    }, [user, view]);
    const applyFilters = async (event) => { event?.preventDefault(); try { await Promise.all([loadBooks(), loadPaidResources()]); } catch (error) { notify('error', error.message); } };
    const refreshUserArea = async (currentUser = user) => { await Promise.all([loadBooks(), loadPaidResources(), loadReadingDocuments(), loadMemberData(currentUser), loadManagedUsers(currentUser)]); };
    const openReadingDocument = async (document) => {
        if (!user) { setPanel('login'); return; }
        if (!document.can_read) { notify('error', 'Empruntez ce livre et payez les frais de 5 % pour ouvrir son PDF.'); return; }
        setSelectedReadingDocument({ ...document, loading_pages: true, pages: [] });
        try {
            const result = await api(`/reading-documents/${document.id}/reader`);
            setSelectedReadingDocument({ ...document, ...result.document, can_read: true, loading_pages: false });
        } catch (error) {
            setSelectedReadingDocument(null);
            notify('error', error.message);
        }
    };
    const openLoanReadingDocument = (document) => openReadingDocument({ ...document, can_read: true });
    const loan = async (book, reservationId = null) => { try { const result = await api('/loans', { method: 'POST', body: JSON.stringify({ book_id: book.id, ...(reservationId ? { reservation_id: reservationId } : {}) }) }); notify('success', `Emprunt enregistré pour 30 jours. Payez les 5 % (${formatPrice(result.loan.fee_amount, result.loan.fee_currency)}) pour lire le PDF en ligne.`); setSelectedBook(null); setView('loans'); await refreshUserArea(); } catch (error) { notify('error', error.message); } };
    const reserve = async (book) => { try { await api(`/books/${book.id}/reservations`, { method: 'POST' }); notify('success', 'Réservation enregistrée. Vous la retrouverez dans Mes réservations.'); setSelectedBook(null); setView('reservations'); await refreshUserArea(); } catch (error) { notify('error', error.message); } };
    const purchaseBook = async (book) => { try { const result = await api(`/books/${book.id}/purchase`, { method: 'POST' }); if (result.download_url) { window.location.href = result.download_url; return; } if (result.payment_url) { window.location.href = result.payment_url; return; } notify('error', 'La commande de ce livre n’a pas pu être créée.'); } catch (error) { notify('error', error.message); } };
    const payLoanFee = async (loanItem) => { try { const result = await api(`/loans/${loanItem.id}/pay-fee`, { method: 'POST' }); if (result.payment_url) { window.location.href = result.payment_url; return; } notify('success', 'Paiement confirmé. Le document est disponible pour la lecture en ligne.'); await refreshUserArea(); } catch (error) { notify('error', error.message); } };
    const cancelReservation = async (reservation) => { try { await api(`/reservations/${reservation.id}`, { method: 'DELETE' }); notify('success', 'Réservation annulée.'); await refreshUserArea(); } catch (error) { notify('error', error.message); } };
    const borrowReservation = async (reservation) => loan(reservation.book, reservation.id);
    const markReturned = async (loanItem) => { try { const result = await api(`/loans/${loanItem.id}/return`, { method: 'POST' }); const returnedLoan = result.loan || {}; const refund = Number(returnedLoan.fee_refund_amount || 0); const charged = Number(returnedLoan.fee_charged_amount || 0); notify('success', refund > 0 ? `Retour enregistré. Facturé ${formatPrice(charged, returnedLoan.fee_currency || 'XOF')}, remboursement ${formatPrice(refund, returnedLoan.fee_currency || 'XOF')}.` : 'Retour enregistré et stock mis à jour.'); await refreshUserArea(); } catch (error) { notify('error', error.message); } };
    const hideReturnedLoan = async (loanItem) => { if (!window.confirm(`Retirer « ${loanItem.book?.title || 'ce document'} » de votre liste ? L’historique restera conservé.`)) return; try { await api(`/loans/${loanItem.id}`, { method: 'DELETE' }); notify('success', 'Le document retourné a été retiré de votre liste.'); await refreshUserArea(); } catch (error) { notify('error', error.message); } };
    const updateReservationStatus = async (reservation, status) => { try { await api(`/reservations/${reservation.id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) }); notify('success', status === 'ready' ? 'Réservation marquée comme prête.' : status === 'waiting' ? 'Réservation remise en attente.' : 'Réservation annulée.'); await loadMemberData(); } catch (error) { notify('error', error.message); } };
    const updateManagedUserRole = async (managedUser, role) => { try { await api(`/users/${managedUser.id}/role`, { method: 'PATCH', body: JSON.stringify({ role }) }); notify('success', `Rôle de ${managedUser.name} mis à jour.`); await loadManagedUsers(); } catch (error) { notify('error', error.message); } };
    const deleteManagedUser = async (managedUser) => { if (!window.confirm(`Supprimer définitivement le compte de ${managedUser.name} ?`)) return; try { await api(`/users/${managedUser.id}`, { method: 'DELETE' }); notify('success', `Compte de ${managedUser.name} supprimé.`); await loadManagedUsers(); } catch (error) { notify('error', error.message); } };
    const logout = async () => { try { await api('/auth/logout', { method: 'POST' }); setUser(null); setDashboard(null); setLoans([]); setReservations([]); setManagedUsers([]); setView('catalog'); notify('success', 'Vous êtes déconnecté.'); } catch (error) { notify('error', error.message); } };
    const navItems = useMemo(() => [{ id: 'catalog', label: 'Catalogue', icon: Library }, ...(user ? [{ id: 'dashboard', label: 'Tableau de bord', icon: LayoutDashboard }, { id: 'loans', label: 'Mes emprunts', icon: CalendarClock }, { id: 'reservations', label: 'Réservations', icon: BookOpen }, ...(user.is_admin ? [{ id: 'users', label: 'Utilisateurs', icon: Users }] : [])] : [])], [user]);
    if (loading) return <div className="app-loading"><Library size={32} /><span>Ouverture de la bibliothèque...</span></div>;
    const bookDocuments = selectedBook ? readingDocuments.filter((document) => document.book_id === selectedBook.id) : [];

    return <div className="library-shell">
        <header className="library-header">
            <a className="library-brand" href="/"><span className="brand-mark"><BookOpen size={20} /></span><span><b>SENI-CNF</b><small>EDU</small></span></a>
            <nav className="main-nav">{navItems.map(({ id, label, icon: Icon }) => <button key={id} className={view === id ? 'active' : ''} type="button" onClick={() => setView(id)}><Icon size={17} />{label}</button>)}</nav>
            <div className="account-actions">{user ? <><span className="user-name"><CircleUserRound size={18} />{user.name}</span><button className="icon-button" title="Se déconnecter" type="button" onClick={logout}><LogOut size={19} /></button></> : <><button className="login-button" type="button" onClick={() => setPanel('login')}><LogIn size={17} />Connexion</button><button className="primary-button compact-button" type="button" onClick={() => setPanel('register')}>Créer un compte</button></>}</div>
        </header>
        <Notice notice={notice} dismiss={() => setNotice(null)} />
        <main className="library-main">
            {view === 'catalog' && <>
                <section className="library-intro"><div><p className="kicker">Ressources documentaires</p><h1>Un catalogue unique pour apprendre.</h1><p>Ressources numériques payantes et livres à emprunter sont réunis au même endroit.</p></div><div className="intro-stat"><ShieldCheck size={28} /><strong>Accès contrôlé</strong><span>Paiements, prêts et lectures sécurisés</span></div></section>
                <form className="catalog-toolbar" onSubmit={applyFilters}><label className="search-field"><Search size={19} /><input value={filters.q} onChange={(event) => setFilters({ ...filters, q: event.target.value })} placeholder="Titre, auteur ou ISBN" /></label><label className="select-field"><Tag size={17} /><select value={filters.category} onChange={(event) => setFilters({ ...filters, category: event.target.value })}><option value="">Toutes les catégories de livres</option>{metadata.categories.map((category) => <option value={category.id} key={category.id}>{category.name}</option>)}</select></label><label className="availability-filter"><input type="checkbox" checked={filters.available} onChange={(event) => setFilters({ ...filters, available: event.target.checked })} />Livres disponibles</label><button className="icon-button search-submit" title="Rechercher" aria-label="Rechercher"><Search size={19} /></button></form>
                <section id="ressources-payantes" className="paid-resources-section"><div className="section-title"><div><p className="kicker">Ressources numériques</p><h2>Documents payants</h2></div></div><div className="paid-resource-list">{paidResources.map((resource) => <PaidResourceCard key={resource.id} resource={resource} />)}</div>{paidResources.length === 0 && <p className="empty-copy">Aucune ressource payante ne correspond à cette recherche.</p>}</section>
                <section id="livres-physiques" className="catalog-section"><div className="section-title"><div><p className="kicker">Bibliothèque physique</p><h2>{books.length} livre{books.length > 1 ? 's' : ''} trouvé{books.length > 1 ? 's' : ''}</h2></div>{user?.is_admin && <AdminAddBookButton onClick={() => { setEditingBook(null); setPanel('book'); }} />}</div><div className="book-grid">{books.map((book) => <BookCard key={book.id} book={book} user={user} documentCount={readingDocuments.filter((document) => document.book_id === book.id).length} selectBook={setSelectedBook} onLoan={loan} onReserve={reserve} onPurchase={purchaseBook} onEdit={(selected) => { setEditingBook(selected); setPanel('book'); }} onAddDocument={(selected) => { setDocumentBook(selected); setPanel('reading-document'); }} />)}</div>{books.length === 0 && <div className="empty-state"><BookOpen size={32} /><p>Aucun livre ne correspond à cette recherche.</p></div>}</section>
            </>}
            {view === 'dashboard' && user && <Dashboard user={user} dashboard={dashboard} loans={loans} onReturn={markReturned} onReservationStatus={updateReservationStatus} onOpenAdminLoan={() => setPanel('admin-loan')} />}
            {view === 'loans' && user && <section className="dashboard"><div className="section-title"><div><p className="kicker">Suivi personnel</p><h2>{user.is_admin ? 'Tous les emprunts' : 'Mes emprunts'}</h2></div></div><div className="table-list">{loans.map((loanItem) => <LoanEntry key={loanItem.id} loanItem={loanItem} user={user} onPayFee={payLoanFee} onReturn={markReturned} onReadDocument={openLoanReadingDocument} onHideReturned={hideReturnedLoan} />)}{loans.length === 0 && <div className="empty-state"><CalendarClock size={32} /><p>Aucun emprunt à afficher.</p></div>}</div></section>}
            {view === 'reservations' && user && <section className="dashboard"><div className="section-title"><div><p className="kicker">Suivi personnel</p><h2>{user.is_admin ? 'Toutes les réservations' : 'Mes réservations'}</h2></div></div><div className="table-list">{reservations.map((reservation) => <ReservationEntry key={reservation.id} reservation={reservation} user={user} onCancel={cancelReservation} onBorrow={borrowReservation} />)}{reservations.length === 0 && <div className="empty-state"><BookOpen size={32} /><p>Aucune réservation à afficher.</p></div>}</div></section>}
            {view === 'users' && user?.is_admin && <UserManagement users={managedUsers} currentUser={user} onRoleChange={updateManagedUserRole} onDelete={deleteManagedUser} />}
        </main>
        <footer className="library-footer"><span><b>SENI-CNF EDU</b> · SAVOIR · SAVOIR-FAIRE · AVENIR</span><a href="/">Ressources éducatives</a></footer>
        {selectedBook && <div className="modal-backdrop" role="presentation" onMouseDown={() => setSelectedBook(null)}><article className="book-modal" role="dialog" aria-modal="true" aria-label={selectedBook.title} onMouseDown={(event) => event.stopPropagation()}><button className="icon-button modal-close" type="button" onClick={() => setSelectedBook(null)} aria-label="Fermer"><X size={20} /></button><Cover book={selectedBook} /><div><p className="category-label">{selectedBook.category?.name}</p><h2>{selectedBook.title}</h2><p className="author-line">{selectedBook.author?.name}</p><p>{selectedBook.description || 'Aucune description disponible.'}</p><dl><div><dt>ISBN</dt><dd>{selectedBook.isbn || 'Non renseigné'}</dd></div><div><dt>Emplacement</dt><dd>{selectedBook.shelf_location || 'Non renseigné'}</dd></div><div><dt>Disponibilité</dt><dd>{selectedBook.available_copies} / {selectedBook.total_copies}</dd></div><div><dt>Prix</dt><dd>{formatPrice(selectedBook.price || 0)}</dd></div><div><dt>Frais d'emprunt</dt><dd>5 % · {formatPrice(borrowingFee(selectedBook))}</dd></div></dl>{bookDocuments.length > 0 && <div className="book-reading-documents"><h3>Aperçu des documents</h3><p>Seule la première page est visible avant paiement des frais de 5 %.</p>{bookDocuments.map((document) => <div className="reading-document-preview-card" key={document.id}><img src={document.preview_url} alt={`Première page de ${document.title}`} onError={(event) => { event.currentTarget.hidden = true; }} /><button className="quiet-action" type="button" onClick={() => openReadingDocument(document)}><FileText size={16} />{document.title}</button></div>)}</div>}{user && <div className="book-card-actions"><button className="primary-button" type="button" onClick={() => selectedBook.available_copies ? loan(selectedBook) : reserve(selectedBook)}>{selectedBook.available_copies ? `Emprunter · frais ${formatPrice(borrowingFee(selectedBook))}` : 'Réserver ce livre'}</button>{bookDocuments.length > 0 && <button className="quiet-action" type="button" onClick={() => purchaseBook(selectedBook)}><Download size={16} />Acheter et télécharger · {formatPrice(selectedBook.price || 0)}</button>}</div>}</div></article></div>}
        {selectedReadingDocument && <div className="modal-backdrop reader-backdrop" role="presentation" onMouseDown={() => setSelectedReadingDocument(null)}><article className="reader-modal" role="dialog" aria-modal="true" aria-label={selectedReadingDocument.title} onMouseDown={(event) => event.stopPropagation()}><header><div><p className="kicker">Lecture après emprunt</p><h2>{selectedReadingDocument.title}</h2></div><button className="icon-button" type="button" onClick={() => setSelectedReadingDocument(null)} aria-label="Fermer"><X size={20} /></button></header><div className="reader-page-list">{selectedReadingDocument.loading_pages ? <p className="reader-loading">Préparation du lecteur...</p> : selectedReadingDocument.pages?.map((page) => <figure className="reader-page" key={page.number}><img src={page.url} alt={`Page ${page.number} de ${selectedReadingDocument.title}`} loading={page.number === 1 ? 'eager' : 'lazy'} /><figcaption>Page {page.number} / {selectedReadingDocument.page_count}</figcaption></figure>)}</div></article></div>}
        {panel && <div className="panel-backdrop" role="presentation" onMouseDown={() => { setPanel(null); setEditingBook(null); setDocumentBook(null); }}><div onMouseDown={(event) => event.stopPropagation()}>{panel === 'book' ? <BookForm metadata={metadata} book={editingBook} onClose={() => { setPanel(null); setEditingBook(null); }} onMetadataChanged={setMetadata} onSaved={() => loadBooks()} notify={notify} /> : panel === 'admin-loan' ? <AdminLoanForm books={books} reservations={dashboard?.reservations || []} onClose={() => setPanel(null)} onCreated={async () => { setPanel(null); await refreshUserArea(); }} notify={notify} /> : panel === 'reading-document' ? <ReadingDocumentForm book={documentBook} onClose={() => { setPanel(null); setDocumentBook(null); }} onSaved={async () => { setPanel(null); setDocumentBook(null); await loadReadingDocuments(); }} notify={notify} /> : <AuthPanel mode={panel} onClose={() => setPanel(null)} onAuthenticated={async (currentUser) => { setUser(currentUser); setPanel(null); await Promise.all([loadMemberData(currentUser), loadReadingDocuments(), loadManagedUsers(currentUser)]); }} notify={notify} />}</div></div>}
    </div>;
}

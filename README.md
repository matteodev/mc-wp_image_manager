<h1>WP IMAGE MANAGER</h1>
<ul>
    <li>CMS: Wordpress</li>
    <li>Ecosystem: Docker </li>
</ul>

<h2>Task Eseguiti</h2>
<ul>
    <li>Creazione ambiente docker (immagini mariaDB e Wordpress + volume DB)</li>
    <li>Implementazione tabelle</li>
    <li>Implementazione options</li>
    <li>Creazione directory repo**</li>
    <li>Popolamento DB con 10 immagini da API Esterna</li>
    <li>Implementazione opzioni di disattivazione / uninstall*</li>
    <li>Creazione di una pagina Image Manager e conversione homepage</li>
    <li>Implementazione dello shortcode</li>
    <li>Implementazione FrontEnd</li>
    <li>Caricamento immagini in repository</li>
    <li>Esclusione di immagini per utente</li>
    <li>Backend pagina Settings per gestire le opzioni</li>
    <li>Rilevamento posizione e Meteo dalla data dello scatto (se disponibile)</li>
    <li>Controllo errori CURL</li>
</ul>
<h1><b>DONE!</b></h1>

<h2>NOTE</h2>
<p>Non avendo nel server locale la possibilità di inviare mail, per gestire l'accesso degli utenti, ho deciso di implementare una sessione basata su cookie:</p>
<p>Ogni volta che un utente accede alla pagina Image Manager, viene generato un ID sessione unico e salvato in un cookie. Questo ID viene utilizzato per identificare l'utente nelle successive interazioni con il sito.</p>
<p>Non è una soluzione perfetta, ma è per dare un'idea in fase dimostrativa.</p>

<p>*La disattivazione fa una pulizia delle tabelle, opzioni, pagine e directory: mi serve in fase di sviluppo per testare: c'è un parametro devmode per disabilitare queste funzioni.</p>
<p>**Il repo viene creato nella cartella upload di WP --> image_manager_repository</p>

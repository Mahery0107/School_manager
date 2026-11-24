<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fournitures scolaires {{ $annee->libelle }}</title>
    <style>
        @page { margin: 16mm 15mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #1c2421; }
        table { width: 100%; border-collapse: collapse; }
        .bandeau td { vertical-align: middle; }
        .bandeau .logo { width: 26mm; }
        .bandeau .logo img { width: 24mm; height: 24mm; }
        .bandeau .ecole { font-size: 15pt; font-weight: bold; color: #1f6f54; text-align: center; padding-right: 26mm; }
        .trait-fin { border-top: 1px solid #1f6f54; margin: 6px 0 10px; }
        .titre { text-align: center; font-size: 14pt; font-weight: bold; letter-spacing: 1px; }
        .sous-titre { text-align: center; margin: 4px 0 16px; font-size: 11pt; }
        .sous-titre strong { font-size: 13pt; color: #1f6f54; }
        .liste th { background: #1f6f54; color: #fff; text-align: left; padding: 6px 8px; font-size: 9.5pt; }
        .liste td { padding: 6px 8px; border-bottom: 1px solid #e2e5e0; vertical-align: top; }
        .liste tr:nth-child(even) td { background: #f6f8f5; }
        .liste .n { width: 9mm; color: #5f6b66; text-align: right; }
        .liste .qte { width: 32mm; text-align: center; font-weight: bold; }
        .liste .rem { width: 55mm; color: #5f6b66; font-size: 9.5pt; }
        .vide { color: #5f6b66; font-style: italic; padding: 12px 0; }
        .note { margin-top: 18px; font-size: 9pt; color: #5f6b66; }
        .saut { page-break-after: always; }
        .pied { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #8a948f; }
    </style>
</head>
<body>
    @php($logo = base64_encode(file_get_contents(resource_path('images/logo.png'))))
    <div class="pied">{{ $ecole }} — Fournitures scolaires {{ $annee->libelle }}</div>

    @foreach ($classes as $classe)
        <table class="bandeau">
            <tr>
                <td class="logo"><img src="data:image/png;base64,{{ $logo }}" alt="Logo"></td>
                <td class="ecole">{{ $ecole }}</td>
            </tr>
        </table>
        <div class="trait-fin"></div>

        <div class="titre">LISTE DES FOURNITURES SCOLAIRES</div>
        <div class="sous-titre">Année scolaire {{ $annee->libelle }} — Classe : <strong>{{ $classe->nom }}</strong></div>

        @php($liste = $fournitures->get($classe->id, collect()))
        @if ($liste->isEmpty())
            <p class="vide">Aucune fourniture n'est demandée pour cette classe.</p>
        @else
            <table class="liste">
                <thead>
                    <tr><th class="n">N°</th><th>Désignation</th><th class="qte">Quantité</th><th class="rem">Remarque</th></tr>
                </thead>
                <tbody>
                    @foreach ($liste as $i => $f)
                        <tr>
                            <td class="n">{{ $i + 1 }}</td>
                            <td>{{ $f->designation }}</td>
                            <td class="qte">{{ $f->quantite }}</td>
                            <td class="rem">{{ $f->remarque }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="note">Merci de marquer chaque fourniture au nom de l'élève.</p>
        @endif

        @if (! $loop->last)
            <div class="saut"></div>
        @endif
    @endforeach
</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu {{ $recu['numero_recu'] }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1c2421; }
        table { width: 100%; border-collapse: collapse; }
        .entete td { vertical-align: top; padding-bottom: 8px; }
        .ecole { font-size: 15pt; font-weight: bold; color: #1f6f54; text-align: center; }
        .trait-fin { border-top: 1px solid #1f6f54; margin: 6px 0 10px; }
        .numero .num { white-space: nowrap; }
        .muted { color: #5f6b66; }
        .titre { font-size: 12pt; font-weight: bold; letter-spacing: 1px; }
        .numero { text-align: right; }
        .numero .num { font-size: 11pt; font-weight: bold; }
        .trait { border-top: 2px solid #1c2421; margin: 4px 0 12px; }
        .infos td { padding: 3px 0; }
        .infos .label { width: 30%; color: #5f6b66; }
        .infos .valeur { font-weight: bold; }
        .lignes { margin-top: 14px; }
        .lignes th { background: #1f6f54; color: #fff; text-align: left; padding: 6px 8px; font-size: 9pt; }
        .lignes td { padding: 6px 8px; border-bottom: 1px solid #e2e5e0; }
        .lignes .montant { text-align: right; white-space: nowrap; }
        .lignes tfoot td { font-weight: bold; font-size: 11pt; border-top: 2px solid #1c2421; border-bottom: 0; }
        .lettres { margin-top: 8px; font-style: italic; }
        .reste { margin-top: 10px; padding: 7px 9px; background: #f4f5f2; }
        .signatures { margin-top: 40px; }
        .signatures td { width: 50%; text-align: center; padding-top: 6px; color: #5f6b66; font-size: 9pt; }
        .signatures .ligne { border-top: 1px solid #9aa39f; margin: 0 18px; padding-top: 4px; }
        .pied { position: fixed; bottom: -6mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #8a948f; }
    </style>
</head>
<body>
    <div class="ecole">{{ $ecole }}</div>
    <div class="trait-fin"></div>
    <table class="entete">
        <tr>
            <td>
                <div class="titre">REÇU DE PAIEMENT</div>
                <div class="muted">Année scolaire {{ $recu['annee_scolaire'] }}</div>
            </td>
            <td class="numero">
                <div class="num">N° {{ $recu['numero_recu'] }}</div>
                <div class="muted">Le {{ \Carbon\Carbon::parse($recu['date_paiement'])->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>
    <div class="trait"></div>

    <table class="infos">
        <tr><td class="label">Élève</td><td class="valeur">{{ $recu['etudiant']->nom }} {{ $recu['etudiant']->prenom }}</td></tr>
        <tr><td class="label">Matricule</td><td class="valeur">{{ $recu['etudiant']->matricule }}</td></tr>
        <tr><td class="label">Classe</td><td class="valeur">{{ $recu['classe'] }}</td></tr>
        @if ($recu['etudiant']->nom_pere)
            <tr><td class="label">Père</td><td>{{ $recu['etudiant']->nom_pere }}@if ($recu['etudiant']->telephone_pere) — {{ $recu['etudiant']->telephone_pere }}@endif</td></tr>
        @endif
        @if ($recu['etudiant']->nom_mere)
            <tr><td class="label">Mère</td><td>{{ $recu['etudiant']->nom_mere }}@if ($recu['etudiant']->telephone_mere) — {{ $recu['etudiant']->telephone_mere }}@endif</td></tr>
        @endif
        <tr>
            <td class="label">Mode de paiement</td>
            <td>{{ $modes[$recu['mode']] ?? $recu['mode'] }}@if($recu['reference']) — réf. {{ $recu['reference'] }}@endif</td>
        </tr>
    </table>

    <table class="lignes">
        <thead>
            <tr><th>Désignation</th><th class="montant">Montant</th></tr>
        </thead>
        <tbody>
            @foreach ($recu['lignes'] as $ligne)
                <tr>
                    <td>
                        @if ($ligne['type'] === 'droit')
                            Droit d'inscription
                        @else
                            Écolage — {{ ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $ligne['mois'].'-01')->locale('fr')->translatedFormat('F Y')) }}
                        @endif
                    </td>
                    <td class="montant">{{ number_format($ligne['montant'], 0, ',', ' ') }} Ar</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td>TOTAL PAYÉ</td><td class="montant">{{ number_format($recu['total'], 0, ',', ' ') }} Ar</td></tr>
        </tfoot>
    </table>

    <div class="reste">
        Situation de l'année : payé {{ number_format($recu['situation']['total_paye'], 0, ',', ' ') }} Ar
        sur {{ number_format($recu['situation']['total_du'], 0, ',', ' ') }} Ar —
        <strong>reste à payer : {{ number_format($recu['situation']['reste'], 0, ',', ' ') }} Ar</strong>
    </div>

    <table class="signatures">
        <tr>
            <td><div class="ligne">Le caissier</div></td>
            <td><div class="ligne">Le parent</div></td>
        </tr>
    </table>

    <div class="pied">Reçu généré le {{ now()->format('d/m/Y à H:i') }} — à conserver</div>
</body>
</html>

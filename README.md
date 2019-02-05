# ZendeskView
Afficher une vue Zendesk sur votre page wordpress
Pas très travaillé pour l'instant, seulement 2 présentations et aucun autre réglage possible.
La présentation en liste est très sommaire (sujets seulement)
La présentation en table reprend les colonnes de votre vue sur Zendesk

## Usage
`[pg_zendesk_view id="360018293851" presentation="liste"]`
- `id` : Identifiant de votre vue Zendesk
- `presentation` : `liste|table` (par défaut, table)

## Configuration
En administration il vous faudra également rentrer les paramètres correspondants à l'API de Zendesk :
- `subdomain`
- `baseurl`
- `username`
- `token`
- `userid`

## TODO
Proposer des réglages sur les vues (choix des champs à afficher...)

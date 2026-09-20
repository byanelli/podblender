/*
   The faint "/" that divides the items of a card's grey metadata row.

   The slash is glued to the text on either side with non-breaking spaces, so a row too long for one line can only
   break inside an item. When the slash is a flex item of its own, or is followed by an ordinary space, a wrapped
   line can begin or end with it, which reads as a mistake.

   Below the sm breakpoint the items stack one per line and the separators are hidden, because a divider between
   lines would only add noise.
*/
export default function MetadataSeparator() {
    return (
        <span aria-hidden="true" className="hidden px-1 opacity-40 sm:inline">
            {' '}/{' '}
        </span>
    );
}

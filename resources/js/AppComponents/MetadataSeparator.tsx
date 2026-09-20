/*
   The "/" between the items of a card's metadata row.

   The spaces around the slash are non-breaking, so a wrapped line never begins or ends with the slash.

   Hidden below the sm breakpoint, where the items stack one per line.
*/
export default function MetadataSeparator() {
    return (
        <span aria-hidden="true" className="hidden px-1 opacity-40 sm:inline">
            {' '}/{' '}
        </span>
    );
}

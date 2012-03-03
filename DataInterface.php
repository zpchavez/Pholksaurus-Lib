<?php
namespace Folksaurus;

/**
 * An interface for working with the Folksaurus term data in your own database.
 */
interface DataInterface
{
    /**
     * Retrieve a term by the ID assigned to it by your application.
     *
     * @param string $id
     * @return array  A term info array suitable for passing into the Term constructor,
     *                or false if not found.
     */
    public function getTermByAppId($id);

    /**
     * Retrieve a term by its Folksaurus-assigned ID.
     *
     * @param int $id
     * @return array  A term info array suitable for passing into the Term constructor,
     *                or false if not found.
     */
    public function getTermByFolksaurusId($id);

    /**
     * Save a term to your database.
     *
     * The term may be new, or it may be an update of a term
     * that already exists in your database.  The app_id
     * should be set if the term already exists.
     *
     * @param Term $term
     */
    public function saveTerm(Term $term);
}

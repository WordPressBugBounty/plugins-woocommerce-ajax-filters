<?php
/**
 * Shared catalog intelligence for both Wizard paths.
 *
 * The detailed Wizard owns filter setup; One-click is only a faster consumer
 * of this service. UI and creation flows must not reimplement analysis,
 * recommendation ranking, or placement selection.
 */
class BeRocket_AAPF_Wizard_Filter_Recommendations {
    /** Return an analysis, building or re-ranking its cache when necessary. */
    public function get_analysis($capability = null) {
        $cache = new BeRocket_AAPF_One_Click_Analysis_Cache();
        return $capability === null ? $cache->get_analysis() : $cache->get_analysis($capability);
    }

    /** Read only: never starts catalog analysis from a page render. */
    public function get_cached_analysis($capability = null) {
        $cache = new BeRocket_AAPF_One_Click_Analysis_Cache();
        return $capability === null ? $cache->get_cached_analysis() : $cache->get_cached_analysis($capability);
    }

    /** A profile change may re-rank this snapshot without querying the catalog. */
    public function has_cached_snapshot() {
        return (new BeRocket_AAPF_One_Click_Analysis_Cache())->has_cached_snapshot();
    }

    /**
     * Normalized read model for Wizard screens. It intentionally has no
     * one-click state, post creation, rollback, or UI assumptions.
     */
    public function get_context($capability = null, $analysis = null) {
        if ($analysis === null) {
            $analysis = $this->get_analysis($capability);
        }
        return $this->build_context($analysis, $capability);
    }

    /** Read-only counterpart for page rendering and progress screens. */
    public function get_cached_context($capability = null) {
        return $this->build_context($this->get_cached_analysis($capability), $capability);
    }

    /** Placement is shared even where a caller already owns an analysis result. */
    public function get_placement_context($capability = null) {
        $desktop_placement = (new BeRocket_AAPF_One_Click_Desktop_Placement())->resolve(null, null, $capability);
        return array(
            'desktop_placement' => $desktop_placement,
            'mobile_placement' => (new BeRocket_AAPF_One_Click_Mobile_Placement())->resolve(array(), $capability, '', $desktop_placement),
        );
    }

    protected function build_context($analysis, $capability) {
        if ($analysis === false || !is_array($analysis)) {
            return false;
        }
        $placements = $this->get_placement_context($capability);
        return array(
            'analysis' => $analysis,
            'recommendations' => isset($analysis['ranking']['recommendations']) && is_array($analysis['ranking']['recommendations'])
                ? $analysis['ranking']['recommendations']
                : array(),
            'desktop_placement' => $placements['desktop_placement'],
            'mobile_placement' => $placements['mobile_placement'],
        );
    }
}

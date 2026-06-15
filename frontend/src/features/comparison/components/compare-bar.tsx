import { AnimatePresence, motion } from "framer-motion";
import { useLocation } from "wouter";
import { X, Columns3, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area";
import { useComparisonStore } from "../stores/comparison-store";
import { useComparisonSchools } from "../hooks/use-comparison-schools";

export function CompareBar() {
  const { schoolIds, isBarVisible, removeSchool, clearAll } = useComparisonStore();
  const { schools } = useComparisonSchools();
  const [, navigate] = useLocation();

  const handleCompare = () => {
    navigate(`/compare?schools=${schoolIds.join(",")}`);
  };

  return (
    <AnimatePresence>
      {isBarVisible && (
        <motion.div
          initial={{ y: 80, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          exit={{ y: 80, opacity: 0 }}
          transition={{ type: "spring", stiffness: 300, damping: 30 }}
          className="fixed bottom-0 left-0 right-0 z-40 border-t bg-card shadow-lg"
          role="region"
          aria-label="School comparison"
        >
          <div className="container mx-auto px-4 py-3">
            <div className="flex items-center gap-3 flex-wrap sm:flex-nowrap">
              <div className="flex items-center gap-2 shrink-0">
                <Columns3 className="h-4 w-4 text-primary" />
                <span className="text-sm font-medium">
                  {schoolIds.length} of 4
                </span>
              </div>

              <ScrollArea className="flex-1 min-w-0">
                <div className="flex items-center gap-2 py-1">
                  <AnimatePresence mode="popLayout">
                    {schools.map((school) => (
                      <motion.div
                        key={school.id}
                        initial={{ scale: 0.8, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        exit={{ scale: 0.8, opacity: 0 }}
                        layout
                      >
                        <Badge
                          variant="secondary"
                          className="gap-1 pr-1 shrink-0 max-w-[180px]"
                        >
                          <span className="truncate text-xs">{school.name}</span>
                          <button
                            onClick={() => removeSchool(school.id)}
                            className="ml-1 rounded-full p-0.5 hover:bg-muted"
                            aria-label={`Remove ${school.name} from comparison`}
                          >
                            <X className="h-3 w-3" />
                          </button>
                        </Badge>
                      </motion.div>
                    ))}
                  </AnimatePresence>
                </div>
                <ScrollBar orientation="horizontal" />
              </ScrollArea>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={clearAll}
                  className="gap-1 text-muted-foreground"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  <span className="hidden sm:inline">Clear</span>
                </Button>
                <Button size="sm" onClick={handleCompare} className="gap-1.5">
                  <Columns3 className="h-3.5 w-3.5" />
                  Compare Now
                </Button>
              </div>
            </div>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

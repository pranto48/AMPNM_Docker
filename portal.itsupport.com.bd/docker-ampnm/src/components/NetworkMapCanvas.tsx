import ReactFlow, {
  MiniMap,
  Controls,
  Background,
  Node,
  Edge,
  Connection,
  NodeDragHandler,
  OnEdgesChange,
  NodeTypes,
} from 'reactflow';
import 'reactflow/dist/style.css';

interface NetworkMapCanvasProps {
  nodes: Node[];
  edges: Edge[];
  onNodesChange: (changes: any[]) => void;
  onEdgesChange: OnEdgesChange;
  onConnect: (connection: Connection) => void;
  nodeTypes: NodeTypes;
  onNodeDragStop: NodeDragHandler;
  onEdgeClick: (event: React.MouseEvent, edge: Edge) => void;
  isAdmin: boolean;
  backgroundStyle: React.CSSProperties;
}

export const NetworkMapCanvas = ({
  nodes,
  edges,
  onNodesChange,
  onEdgesChange,
  onConnect,
  nodeTypes,
  onNodeDragStop,
  onEdgeClick,
  isAdmin,
  backgroundStyle,
}: NetworkMapCanvasProps) => {
  return (
    <div style={{ height: '70vh', width: '100%', ...backgroundStyle }} className="relative border rounded-lg bg-gray-900">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        nodeTypes={nodeTypes}
        onNodeDragStop={onNodeDragStop}
        onEdgeClick={onEdgeClick}
        fitView
        fitViewOptions={{ padding: 0.1 }}
        proOptions={{ hideAttribution: true }}
        nodesDraggable={isAdmin}
        nodesConnectable={isAdmin}
        elementsSelectable={isAdmin}
      >
        <Controls />
        <MiniMap
          nodeColor={(n) => {
            switch (n.data.status) {
              case 'online': return '#22c55e';
              case 'offline': return '#ef4444';
              default: return '#94a3b8';
            }
          }}
          nodeStrokeWidth={3}
          maskColor="rgba(15, 23, 42, 0.8)"
        />
        <Background gap={16} size={1} color="#444" />
      </ReactFlow>
    </div>
  );
};